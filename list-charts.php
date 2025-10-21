<?php
require_once 'config.php';

$repoName = $_GET['repo'] ?? '';
if (empty($repoName)) {
    header('Location: index.php');
    exit;
}

// Cache-dən oxumaq
$cacheKey = "repo_charts_{$repoName}";
$charts = getCachedData($cacheKey);

if (!$charts) {
    // Helm-dən chart siyahısını almaq
    $result = runHelmCommand("search repo {$repoName}/");
    
    $charts = [];
    if ($result['success']) {
        foreach ($result['output'] as $index => $line) {
            if ($index === 0 || trim($line) === '') continue;
            
            $parts = preg_split('/\s+/', $line);
            if (count($parts) >= 4) {
                $chartFullName = $parts[0];
                $chartNameOnly = str_replace("{$repoName}/", "", $chartFullName);
                $charts[] = [
                    'full_name' => $chartFullName,
                    'name' => $chartNameOnly,
                    'version' => $parts[1],
                    'app_version' => $parts[2],
                    'description' => implode(' ', array_slice($parts, 3))
                ];
            }
        }
        setCachedData($cacheKey, $charts);
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($repoName) ?> Chart-ları - Helm Repo Manager</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?= htmlspecialchars($repoName) ?> - Chart Siyahısı</h1>
            <a href="index.php" class="btn btn-secondary">Geri</a>
        </header>

        <?php if (empty($charts)): ?>
            <div class="alert alert-info">
                Bu repo-da heç bir chart tapılmadı.
            </div>
        <?php else: ?>
            <div class="charts-info">
                <p><strong><?= count($charts) ?></strong> chart tapıldı</p>
            </div>
            
            <div class="charts-table">
                <table>
                    <thead>
                        <tr>
                            <th>Chart Adı</th>
                            <th>Versiya</th>
                            <th>App Versiya</th>
                            <th>Təsvir</th>
                            <th>Əməliyyatlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($charts as $chart): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($chart['name']) ?></strong></td>
                                <td><span class="version"><?= htmlspecialchars($chart['version']) ?></span></td>
                                <td><span class="app-version"><?= htmlspecialchars($chart['app_version']) ?></span></td>
                                <td class="description"><?= htmlspecialchars($chart['description']) ?></td>
                                <td>
                                    <a href="chart-edit.php?repo=<?= urlencode($repoName) ?>&chart=<?= urlencode($chart['name']) ?>&version=<?= urlencode($chart['version']) ?>" 
                                       class="btn btn-small btn-warning">Redaktə Et</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
