<?php
require_once 'config.php';

$repoName = $_GET['repo'] ?? '';
$chartName = $_GET['chart'] ?? '';
$version = $_GET['version'] ?? '';
$filePath = $_GET['file'] ?? '';
$action = $_GET['action'] ?? '';

$message = '';
$messageType = '';
$chartDir = '';

// Chart pull et
if ($action === 'pull') {
    if (!empty($repoName) && !empty($chartName) && !empty($version)) {
        $chartFullName = "{$repoName}/{$chartName}";
        $chartDir = pullChart($chartFullName, $version);
    } elseif (!empty($chartName) && !empty($version)) {
        $chartDir = pullChart($chartName, $version);
    } else {
        $message = 'Chart adı və versiya tələb olunur!';
        $messageType = 'error';
    }
    
    if ($chartDir) {
        $message = 'Chart uğurla pull edildi!';
        $messageType = 'success';
        header("Location: chart-edit.php?repo=" . urlencode($repoName) . "&chart=" . urlencode($chartName) . "&version=" . urlencode($version));
        exit;
    } else if (empty($message)) {
        $message = 'Chart pull edilərkən xəta baş verdi!';
        $messageType = 'error';
    }
}

// Chart qovluğunu tap
if (empty($chartDir) && !empty($repoName) && !empty($chartName) && !empty($version)) {
    $searchName = !empty($repoName) ? "{$repoName}/{$chartName}" : $chartName;
    $chartDir = findChartDirectory($searchName, $version);
}

// POST action-larını emal et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'save_file' && !empty($_POST['file_path']) && isset($_POST['file_content'])) {
        if ($chartDir && writeChartFile($chartDir, $_POST['file_path'], $_POST['file_content'])) {
            $message = 'Fayl uğurla saxlanıldı!';
            $messageType = 'success';
        } else {
            $message = 'Fayl saxlanılarkən xəta baş verdi!';
            $messageType = 'error';
        }
    }
    
    if ($postAction === 'package_chart') {
        if ($chartDir && packageChart($chartDir)) {
            $message = 'Chart uğurla package edildi! Yeni .tgz faylı charts qovluğunda yaradıldı.';
            $messageType = 'success';
        } else {
            $message = 'Chart package edilərkən xəta baş verdi!';
            $messageType = 'error';
        }
    }
}

if ($chartDir) {
    $chartStructure = getChartStructure($chartDir);
    
    // Cari faylın məzmununu oxu
    $currentFileContent = '';
    $fileReadError = false;
    
    if (!empty($filePath)) {
        $currentFileContent = readChartFile($chartDir, $filePath);
        if ($currentFileContent === false) {
            $fileReadError = true;
            $message = 'Fayl oxuna bilmədi: ' . htmlspecialchars($filePath);
            $messageType = 'error';
        }
    }
} else {
    $chartStructure = [];
    $currentFileContent = '';
    $fileReadError = false;
}

// Fayl strukturunu folder formatında qrupla
$fileTree = buildFileTreeFromStructure($chartStructure);
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart Redaktə - Helm Repo Manager</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Chart Redaktə</h1>
            <div class="header-actions">
                <!-- YALNIZ axtarışdan gələnlər üçün "Versiyalara Qayıt" -->
                <?php if (empty($repoName) && !empty($chartName)): ?>
                    <a href="chart-search-versions.php?chart=<?= urlencode($chartName) ?>" class="btn btn-info">Versiyalara Qayıt</a>
                <?php endif; ?>
                <a href="search.php" class="btn btn-secondary">Axtarışa Qayıt</a>
                <a href="index.php" class="btn btn-secondary">Əsas Səhifə</a>
            </div>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($chartDir): ?>
            <div class="chart-editor">
                <div class="chart-info">
                    <h2><?= htmlspecialchars($chartName) ?> (v<?= htmlspecialchars($version) ?>)</h2>
                    <?php if (!empty($repoName)): ?>
                        <p><strong>Repo:</strong> <?= htmlspecialchars($repoName) ?></p>
                        <p><strong>Mənbə:</strong> Local Repo</p>
                    <?php else: ?>
                        <p><strong>Mənbə:</strong> Helm Hub</p>
                    <?php endif; ?>
                    <p><strong>Versiya:</strong> <span class="version-badge"><?= htmlspecialchars($version) ?></span></p>
                    <p><strong>Qovluq:</strong> <?= htmlspecialchars(basename($chartDir)) ?></p>
                    <p><strong>Status:</strong> <span class="status-active">Aktiv</span></p>
                </div>

                <div class="editor-layout">
                    <div class="file-structure">
                        <h3>Fayl Strukturu</h3>
                        <div class="file-tree">
                            <?php
                            if (empty($fileTree)) {
                                echo '<div class="alert alert-info">Heç bir fayl tapılmadı</div>';
                            } else {
                                displayFileTree($fileTree, $filePath, $repoName, $chartName, $version);
                            }
                            ?>
                        </div>
                        
                        <div class="chart-actions">
                            <form method="POST">
                                <input type="hidden" name="action" value="package_chart">
                                <button type="submit" class="btn btn-primary">Chart Package Et</button>
                            </form>
                            <div style="margin-top: 10px; font-size: 12px; color: #666;">
                                Package edildikdə yeni .tgz faylı charts qovluğunda yaradılacaq.
                            </div>
                        </div>
                    </div>

                    <div class="file-editor">
                        <?php if (!empty($filePath)): ?>
                            <h3>Redaktə: <?= htmlspecialchars($filePath) ?></h3>
                            <?php if (!$fileReadError && $currentFileContent !== false): ?>
                                <form method="POST" id="fileForm">
                                    <input type="hidden" name="action" value="save_file">
                                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($filePath) ?>">
                                    
                                    <div class="form-group">
                                        <textarea id="fileContent" name="file_content" rows="20" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"><?= htmlspecialchars($currentFileContent) ?></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Saxla</button>
                                        <a href="chart-edit.php?<?= http_build_query(['repo' => $repoName, 'chart' => $chartName, 'version' => $version]) ?>" class="btn btn-secondary">Ləğv Et</a>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-error">
                                    <h4>Fayl oxuna bilmədi!</h4>
                                    <p>Fayl: <?= htmlspecialchars($filePath) ?></p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-file-selected">
                                <h3>Fayl Seçin</h3>
                                <p>Sol tərəfdən redaktə etmək istədiyiniz faylı seçin.</p>
                                <div class="common-files">
                                    <h4>Əsas Fayllar:</h4>
                                    <ul>
                                        <li><strong>Chart.yaml</strong> - Chart metadata</li>
                                        <li><strong>values.yaml</strong> - Default configuration values</li>
                                        <li><strong>templates/</strong> - Kubernetes manifest faylları</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <h3>Chart Tapılmadı</h3>
                <p>Redaktə etmək üçün əvvəlcə chart-i pull etməlisiniz.</p>
                <?php if (!empty($chartName) && !empty($version)): ?>
                    <div class="pull-action" style="margin-top: 15px;">
                        <a href="chart-edit.php?action=pull&repo=<?= urlencode($repoName) ?>&chart=<?= urlencode($chartName) ?>&version=<?= urlencode($version) ?>" 
                           class="btn btn-primary">Chart-i Pull Et</a>
                        <div style="margin-top: 10px; font-size: 14px; color: #666;">
                            <strong>Qeyd:</strong> Hər versiya üçün ayrıca folder yaradılacaq.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning" style="margin-top: 15px;">
                        Chart adı və ya versiya müəyyən edilməyib.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Köməkçi funksiyalar

// Fayl strukturunu folder ağacına çevir
function buildFileTreeFromStructure($structure) {
    $tree = [];
    
    foreach ($structure as $file) {
        $pathParts = explode('/', $file['path']);
        $current = &$tree;
        
        foreach ($pathParts as $i => $part) {
            if ($i === count($pathParts) - 1) {
                // Son hissə - fayl
                $current[] = [
                    'type' => 'file',
                    'name' => $part,
                    'path' => $file['path'],
                    'info' => $file
                ];
            } else {
                // Qovluq
                $folderFound = false;
                foreach ($current as &$item) {
                    if ($item['type'] === 'folder' && $item['name'] === $part) {
                        $current = &$item['children'];
                        $folderFound = true;
                        break;
                    }
                }
                
                if (!$folderFound) {
                    $current[] = [
                        'type' => 'folder',
                        'name' => $part,
                        'children' => []
                    ];
                    $current = &$current[count($current)-1]['children'];
                }
            }
        }
    }
    
    return $tree;
}

// Fayl ağacını göstər
function displayFileTree($tree, $currentFile, $repoName, $chartName, $version, $level = 0) {
    foreach ($tree as $item) {
        $padding = $level * 20;
        $isCurrent = ($currentFile === ($item['path'] ?? ''));
        
        if ($item['type'] === 'file') {
            echo '<div class="file-item" style="padding-left: ' . $padding . 'px;">';
            echo '<a href="?repo=' . urlencode($repoName) . '&chart=' . urlencode($chartName) . '&version=' . urlencode($version) . '&file=' . urlencode($item['path']) . '" class="file-link ' . ($isCurrent ? 'current' : '') . '">';
            echo '📄 ' . htmlspecialchars($item['name']);
            echo '</a>';
            echo '</div>';
        } else {
            // Qovluq
            echo '<div class="folder-item" style="padding-left: ' . $padding . 'px;">';
            echo '📁 ' . htmlspecialchars($item['name']);
            echo '</div>';
            // Rekursiv şəkildə uşaq elementləri göstər
            if (!empty($item['children'])) {
                displayFileTree($item['children'], $currentFile, $repoName, $chartName, $version, $level + 1);
            }
        }
    }
}
