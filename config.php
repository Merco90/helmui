<?php
// Error reporting aktiv et
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-errors.log');

// Helm Repo Manager Configuration
define('HELM_BINARY', '/usr/bin/helm');
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_TIMEOUT', 300);
define('REPOS_FILE', __DIR__ . '/repos.json');
define('CHARTS_DIR', __DIR__ . '/charts');

// Helm qovluqları
define('HELM_CACHE_DIR', __DIR__ . '/helm-cache');
define('HELM_CONFIG_DIR', __DIR__ . '/helm-config');
define('HELM_REPOS_FILE', HELM_CONFIG_DIR . '/repositories.yaml');

// Lazımi qovluqları yaratmaq
$directories = [CACHE_DIR, HELM_CACHE_DIR, HELM_CONFIG_DIR, CHARTS_DIR];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Qovluq yaradıla bilmədi: " . $dir);
        }
    }
}

// Repos faylını yoxlamaq
if (!file_exists(REPOS_FILE)) {
    file_put_contents(REPOS_FILE, json_encode([]));
}

// Helm repositories.yaml faylını yaratmaq
if (!file_exists(HELM_REPOS_FILE)) {
    $initialRepositories = "apiVersion: \"\"\ngenerated: \"0001-01-01T00:00:00Z\"\nrepositories: []\n";
    file_put_contents(HELM_REPOS_FILE, $initialRepositories);
}

// SADƏ Helm əmrlərini icra etmək funksiyası
function runHelmCommand($command) {
    // Environment dəyişənlərini təyin et
    putenv('HELM_CACHE_HOME=' . HELM_CACHE_DIR);
    putenv('HELM_CONFIG_HOME=' . HELM_CONFIG_DIR);
    
    $fullCommand = HELM_BINARY . ' ' . $command . ' 2>&1';
    
    $output = [];
    $returnCode = 0;
    
    exec($fullCommand, $output, $returnCode);
    
    error_log("Helm Command: " . $fullCommand);
    error_log("Helm Return Code: " . $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'output' => $output,
        'returnCode' => $returnCode
    ];
}

// Chart pull etmək funksiyası (YENİ - versiya-based folder)
function pullChart($chartName, $version = null) {
    if (!$version) {
        error_log("Version is required for pullChart");
        return false;
    }
    
    $versionParam = "--version {$version}";
    
    // Əvvəlcə repo update et
    runHelmCommand("repo update");
    
    // Chart üçün versiya-based folder yarat
    $safeChartName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $chartName);
    $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $version);
    $chartDir = CHARTS_DIR . '/' . $safeChartName . '-' . $safeVersion;
    
    // Əgər folder artıq varsa, silib yenidən yarat
    if (file_exists($chartDir)) {
        deleteDirectory($chartDir);
    }
    
    // Yeni folder yarat
    if (!mkdir($chartDir, 0755, true)) {
        error_log("Cannot create chart directory: " . $chartDir);
        return false;
    }
    
    $pullCommand = "pull {$chartName} {$versionParam} --untar --destination " . $chartDir;
    
    error_log("Pull command: " . $pullCommand);
    $result = runHelmCommand($pullCommand);
    
    if ($result['success']) {
        // Chart qovluğunu tap (içindəki chart qovluğunu)
        $actualChartDir = findActualChartDirectory($chartDir, $chartName);
        if ($actualChartDir) {
            error_log("Chart found at: " . $actualChartDir);
            return $actualChartDir;
        } else {
            error_log("Actual chart directory not found in: " . $chartDir);
            return $chartDir;
        }
    } else {
        error_log("Pull failed: " . implode("\n", $result['output']));
        // Folderu təmizlə
        deleteDirectory($chartDir);
        return false;
    }
}

// Qovluğu rekursiv silmək
function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}

// Əsl chart qovluğunu tapmaq
function findActualChartDirectory($baseDir, $chartName) {
    $files = scandir($baseDir);
    $chartBaseName = basename($chartName);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $fullPath = $baseDir . '/' . $file;
        
        if (is_dir($fullPath)) {
            // Chart.yaml faylı var?
            if (file_exists($fullPath . '/Chart.yaml')) {
                return $fullPath;
            }
            
            // Chart adı ilə uyğunluq
            if (strpos($file, $chartBaseName) !== false) {
                return $fullPath;
            }
        }
    }
    
    // Əgər tapılmadısa, baseDir-də Chart.yaml var?
    if (file_exists($baseDir . '/Chart.yaml')) {
        return $baseDir;
    }
    
    return false;
}

// Chart qovluğunu tapmaq (YENİ - versiya-based axtarış)
function findChartDirectory($chartName, $version = null) {
    if (!$version) {
        return false;
    }
    
    $safeChartName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $chartName);
    $safeVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $version);
    $chartDir = CHARTS_DIR . '/' . $safeChartName . '-' . $safeVersion;
    
    if (file_exists($chartDir) && is_dir($chartDir)) {
        // Əsl chart qovluğunu tap
        $actualChartDir = findActualChartDirectory($chartDir, $chartName);
        return $actualChartDir ?: $chartDir;
    }
    
    return false;
}

// Chart fayl strukturunu oxumaq
function getChartStructure($chartPath) {
    $structure = [];
    
    if (!file_exists($chartPath)) {
        return $structure;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($chartPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($chartPath . '/', '', $file->getPathname());
            $structure[] = [
                'path' => $relativePath,
                'size' => $file->getSize(),
                'modified' => $file->getMTime()
            ];
        }
    }
    
    return $structure;
}

// Chart faylının məzmununu oxumaq
function readChartFile($chartPath, $filePath) {
    $fullPath = $chartPath . '/' . $filePath;
    
    error_log("Trying to read file: " . $fullPath);
    
    if (!file_exists($fullPath)) {
        error_log("File does not exist: " . $fullPath);
        return false;
    }
    
    if (!is_readable($fullPath)) {
        error_log("File is not readable: " . $fullPath);
        chmod($fullPath, 0644);
        if (!is_readable($fullPath)) {
            error_log("Still not readable after chmod: " . $fullPath);
            return false;
        }
    }
    
    $content = file_get_contents($fullPath);
    if ($content === false) {
        error_log("Failed to read file content: " . $fullPath);
        return false;
    }
    
    error_log("Successfully read file: " . $fullPath . " (" . strlen($content) . " bytes)");
    return $content;
}

// Chart faylının məzmununu yazmaq
function writeChartFile($chartPath, $filePath, $content) {
    $fullPath = $chartPath . '/' . $filePath;
    
    // Qovluğu yoxla və yarat
    $dir = dirname($fullPath);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    if (file_put_contents($fullPath, $content) !== false) {
        chmod($fullPath, 0644);
        return true;
    }
    
    return false;
}

// Chart package etmək (YENİ - versiya-based package)
function packageChart($chartPath) {
    $originalDir = getcwd();
    chdir($chartPath);
    
    $result = runHelmCommand("package .");
    
    chdir($originalDir);
    
    if ($result['success']) {
        // Package edilmiş faylı charts qovluğuna köçür
        $tgzFiles = glob($chartPath . '/*.tgz');
        foreach ($tgzFiles as $tgzFile) {
            $newLocation = CHARTS_DIR . '/' . basename($tgzFile);
            if (rename($tgzFile, $newLocation)) {
                error_log("Package moved to: " . $newLocation);
            }
        }
    }
    
    return $result['success'];
}

// Tarix məlumatını gətirmək
function getChartVersionDate($repoName, $chartName, $version) {
    $cacheKey = "chart_date_{$repoName}_{$chartName}_{$version}";
    $cachedDate = getCachedData($cacheKey);
    
    if ($cachedDate) {
        return $cachedDate;
    }
    
    $date = 'Məlumat yoxdur';
    
    try {
        $result = runHelmCommand("show chart {$repoName}/{$chartName} --version {$version}");
        
        if ($result['success']) {
            foreach ($result['output'] as $line) {
                if (strpos($line, 'created:') !== false) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $dateStr = trim($parts[1]);
                        if (!empty($dateStr)) {
                            $date = formatHelmDate($dateStr);
                            break;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting chart date: " . $e->getMessage());
    }
    
    setCachedData($cacheKey, $date);
    return $date;
}

// Helm tarixini formatlamaq
function formatHelmDate($dateString) {
    try {
        $date = new DateTime($dateString);
        return $date->format('d.m.Y H:i:s');
    } catch (Exception $e) {
        return $dateString;
    }
}

// Helm Hub chart-inin tarix məlumatını gətirmək
function getHubChartVersionDate($chartName, $version) {
    $cacheKey = "hub_chart_date_{$chartName}_{$version}";
    $cachedDate = getCachedData($cacheKey);
    
    if ($cachedDate) {
        return $cachedDate;
    }
    
    $date = 'Məlumat yoxdur';
    
    try {
        $result = runHelmCommand("show chart {$chartName} --version {$version}");
        
        if ($result['success']) {
            foreach ($result['output'] as $line) {
                if (strpos($line, 'created:') !== false) {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $dateStr = trim($parts[1]);
                        if (!empty($dateStr)) {
                            $date = formatHelmDate($dateStr);
                            break;
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting hub chart date: " . $e->getMessage());
    }
    
    setCachedData($cacheKey, $date);
    return $date;
}

// Helm Hub-dan chart versiyalarını gətirmək
function getSearchChartVersions($chartName) {
    $cacheKey = "search_versions_{$chartName}";
    $versions = getCachedData($cacheKey);
    
    if (!$versions) {
        $versions = [];
        
        $searchResult = runHelmCommand("search hub {$chartName} --max-col-width 0");
        
        if ($searchResult['success']) {
            foreach ($searchResult['output'] as $index => $line) {
                if ($index === 0 || trim($line) === '') continue;
                
                if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)\s+(.*)$/', $line, $matches)) {
                    if (count($matches) >= 5) {
                        $chartFullName = $matches[1];
                        $version = $matches[2];
                        
                        $date = getHubChartVersionDate($chartFullName, $version);
                        
                        $versions[] = [
                            'name' => $chartFullName,
                            'version' => $version,
                            'app_version' => $matches[3],
                            'description' => $matches[4],
                            'created' => $date,
                            'source' => 'hub'
                        ];
                    }
                }
            }
        }
        
        setCachedData($cacheKey, $versions);
    }
    
    return $versions;
}

// Repo əlavə etmək funksiyası
function addHelmRepo($repoName, $repoUrl) {
    $result = runHelmCommand("repo add {$repoName} {$repoUrl}");
    
    if ($result['success']) {
        updateRepositoriesFile();
        return true;
    }
    
    return false;
}

// Repo silmək funksiyası
function removeHelmRepo($repoName) {
    $result = runHelmCommand("repo remove {$repoName}");
    
    if ($result['success']) {
        updateRepositoriesFile();
        
        $repos = [];
        if (file_exists(REPOS_FILE)) {
            $reposData = file_get_contents(REPOS_FILE);
            if ($reposData !== false) {
                $repos = json_decode($reposData, true) ?: [];
            }
        }
        
        $repos = array_filter($repos, function($repo) use ($repoName) {
            return $repo['name'] !== $repoName;
        });
        
        $repos = array_values($repos);
        file_put_contents(REPOS_FILE, json_encode($repos));
        
        $cacheFiles = glob(CACHE_DIR . "/*.cache");
        if ($cacheFiles !== false) {
            foreach ($cacheFiles as $cacheFile) {
                unlink($cacheFile);
            }
        }
        
        return true;
    }
    
    return false;
}

// Chart-in bütün versiyalarını gətirmək funksiyası
function getChartVersions($repoName, $chartName) {
    $cacheKey = "chart_versions_{$repoName}_{$chartName}";
    $versions = getCachedData($cacheKey);
    
    if (!$versions) {
        $versions = [];
        
        $result = runHelmCommand("search repo {$repoName}/{$chartName} --versions");
        
        if ($result['success']) {
            foreach ($result['output'] as $index => $line) {
                if ($index === 0 || trim($line) === '') continue;
                
                $parts = preg_split('/\s+/', $line, 4);
                if (count($parts) >= 4) {
                    $version = $parts[1];
                    
                    $date = getChartVersionDate($repoName, $chartName, $version);
                    
                    $versions[] = [
                        'name' => $parts[0],
                        'version' => $version,
                        'app_version' => $parts[2],
                        'description' => $parts[3] ?? '',
                        'created' => $date
                    ];
                }
            }
        }
        
        setCachedData($cacheKey, $versions);
    }
    
    return $versions;
}

// Repositories faylını yeniləmək
function updateRepositoriesFile() {
    $result = runHelmCommand('repo list');
    
    if ($result['success']) {
        $repositories = [];
        foreach ($result['output'] as $line) {
            if (strpos($line, 'NAME') === 0 || trim($line) === '') continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 2) {
                $repositories[] = [
                    'name' => $parts[0],
                    'url' => $parts[1]
                ];
            }
        }
        
        $yamlContent = "apiVersion: \"\"\n";
        $yamlContent .= "generated: \"" . date('c') . "\"\n";
        $yamlContent .= "repositories:\n";
        
        foreach ($repositories as $repo) {
            $yamlContent .= "- name: \"{$repo['name']}\"\n";
            $yamlContent .= "  url: \"{$repo['url']}\"\n";
        }
        
        file_put_contents(HELM_REPOS_FILE, $yamlContent);
    }
}

// Cache funksiyaları
function getCachedData($key) {
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.cache';
    
    if (file_exists($cacheFile) && 
        (time() - filemtime($cacheFile)) < CACHE_TIMEOUT) {
        $data = file_get_contents($cacheFile);
        if ($data === false) {
            error_log("Cache faylı oxuna bilmədi: " . $cacheFile);
            return null;
        }
        return json_decode($data, true);
    }
    
    return null;
}

function setCachedData($key, $data) {
    $cacheFile = CACHE_DIR . '/' . md5($key) . '.cache';
    if (file_put_contents($cacheFile, json_encode($data)) === false) {
        error_log("Cache faylı yazıla bilmədi: " . $cacheFile);
    }
}
?>
