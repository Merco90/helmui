<?php
require_once 'config.php';

$repoName = $_GET['repo'] ?? '';
$chartName = $_GET['chart'] ?? '';

if (empty($repoName) || empty($chartName)) {
    header('Location: index.php');
    exit;
}

$versions = getChartVersions($repoName, $chartName);
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($repoName) ?>/<?= htmlspecialchars($chartName) ?> - Bütün Versiyalar - Helm Repo Manager</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?= htmlspecialchars($repoName) ?>/<?= htmlspecialchars($chartName) ?> - Bütün Versiyalar</h1>
            <div class="header-actions">
                <a href="list-charts.php?repo=<?= urlencode($repoName) ?>" class="btn btn-secondary">Chart Siyahısı</a>
                <a href="index.php" class="btn btn-secondary">Əsas Səhifə</a>
            </div>
        </header>

        <div class="chart-info">
            <h2>Chart: <?= htmlspecialchars($chartName) ?></h2>
            <p><strong>Repo:</strong> <?= htmlspecialchars($repoName) ?></p>
            <p><strong>Versiya Sayı:</strong> <?= count($versions) ?></p>
        </div>

        <?php if (empty($versions)): ?>
            <div class="alert alert-info">
                Bu chart üçün heç bir versiya tapılmadı.
            </div>
        <?php else: ?>
            <div class="versions-table">
                <h3>Bütün Versiyalar</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Versiya</th>
                            <th>App Versiya</th>
                            <th>Chart Adı</th>
                            <th>Təsvir</th>
                            <th>Əməliyyatlar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($versions as $version): ?>
                            <tr>
                                <td>
                                    <span class="version version-badge"><?= htmlspecialchars($version['version']) ?></span>
                                </td>
                                <td>
                                    <span class="app-version"><?= htmlspecialchars($version['app_version']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($version['name']) ?></strong>
                                </td>
                                <td class="description">
                                    <?= htmlspecialchars($version['description']) ?>
                                </td>
                                <td>
                                    <a href="chart-edit.php?repo=<?= urlencode($repoName) ?>&chart=<?= urlencode($chartName) ?>&version=<?= urlencode($version['version']) ?>" 
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
