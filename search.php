<?php
require_once 'config.php';

$searchResults = [];
$searchQuery = $_GET['q'] ?? '';

if (!empty($searchQuery)) {
    $cacheKey = "search_{$searchQuery}";
    $searchResults = getCachedData($cacheKey);
    
    if (!$searchResults) {
        // Əvvəlcə local repo-larda axtar
        $result = runHelmCommand("search repo {$searchQuery}");
        
        if ($result['success']) {
            $searchResults = [];
            foreach ($result['output'] as $index => $line) {
                if ($index === 0 || trim($line) === '') continue;
                
                $parts = preg_split('/\s+/', $line, 4);
                if (count($parts) >= 4) {
                    $chartFullName = $parts[0];
                    if (strpos($chartFullName, '/') !== false) {
                        list($repoName, $chartName) = explode('/', $chartFullName, 2);
                    } else {
                        $repoName = 'unknown';
                        $chartName = $chartFullName;
                    }
                    
                    $searchResults[] = [
                        'name' => $chartFullName,
                        'repo' => $repoName,
                        'chart_name' => $chartName,
                        'version' => $parts[1],
                        'app_version' => $parts[2],
                        'description' => $parts[3] ?? '',
                        'source' => 'local'
                    ];
                }
            }
        }
        
        // Helm Hub axtarışı
        $hubResult = runHelmCommand("search hub {$searchQuery} --max-col-width 0");
        
        if ($hubResult['success']) {
            foreach ($hubResult['output'] as $index => $line) {
                if ($index === 0 || trim($line) === '') continue;
                
                preg_match('/^(\S+)\s+(\S+)\s+(\S+)\s+(.*)$/', $line, $matches);
                if (count($matches) >= 5) {
                    $searchResults[] = [
                        'name' => $matches[1],
                        'repo' => 'hub',
                        'chart_name' => $matches[1],
                        'version' => $matches[2],
                        'app_version' => $matches[3],
                        'description' => $matches[4],
                        'source' => 'hub'
                    ];
                }
            }
        }
        
        setCachedData($cacheKey, $searchResults);
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart Axtarışı - Helm Repo Manager</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Chart Axtarışı</h1>
            <a href="index.php" class="btn btn-secondary">Əsas Səhifəyə Qayıt</a>
        </header>

        <div class="search-form">
            <form method="GET" action="search.php">
                <div class="form-group">
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" 
                           placeholder="Chart adı daxil edin (məs: nginx, mysql, redis)" 
                           style="padding: 12px; width: 70%; font-size: 16px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Axtar</button>
                </div>
            </form>
        </div>

        <?php if (!empty($searchQuery)): ?>
            <div class="search-results">
                <h2>"<?= htmlspecialchars($searchQuery) ?>" üçün axtarış nəticələri</h2>
                
                <?php if (empty($searchResults)): ?>
                    <div class="alert alert-info">
                        Heç bir nəticə tapılmadı. Axtarış sözünü dəyişib yenidən cəhd edin.
                    </div>
                <?php else: ?>
                    <div class="results-info">
                        <p><strong><?= count($searchResults) ?></strong> nəticə tapıldı</p>
                    </div>
                    
                    <div class="charts-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Chart Adı</th>
                                    <th>Versiya</th>
                                    <th>App Versiya</th>
                                    <th>Mənbə</th>
                                    <th>Təsvir</th>
                                    <th>Əməliyyatlar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($searchResults as $chart): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($chart['name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="version"><?= htmlspecialchars($chart['version']) ?></span>
                                        </td>
                                        <td>
                                            <span class="app-version"><?= htmlspecialchars($chart['app_version']) ?></span>
                                        </td>
                                        <td>
                                            <span class="source <?= $chart['source'] ?>">
                                                <?= $chart['source'] === 'local' ? 'Local Repo' : 'Helm Hub' ?>
                                            </span>
                                        </td>
                                        <td class="description">
                                            <?= htmlspecialchars($chart['description']) ?>
                                        </td>
                                        <td class="actions">
                                            <?php if ($chart['source'] === 'local'): ?>
                                                <a href="chart-versions.php?repo=<?= urlencode($chart['repo']) ?>&chart=<?= urlencode($chart['chart_name']) ?>" 
                                                   class="btn btn-small btn-info">Bütün Versiyalar</a>
                                            <?php else: ?>
                                                <a href="chart-search-versions.php?chart=<?= urlencode($chart['name']) ?>" 
                                                   class="btn btn-small btn-info">Bütün Versiyalar</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="search-help">
                <div class="alert alert-info">
                    <h3>Axtarış Köməyi</h3>
                    <p>Chart adını daxil edərək axtarış edə bilərsiniz.</p>
                    
                    <h4>Populyar Chart-lar:</h4>
                    <div class="popular-charts">
                        <?php
                        $popularCharts = ['nginx', 'mysql', 'redis', 'postgresql', 'mongodb', 'wordpress'];
                        foreach ($popularCharts as $chart): ?>
                            <a href="search.php?q=<?= urlencode($chart) ?>" class="chart-tag">
                                <?= $chart ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
