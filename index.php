<?php
require_once 'config.php';

// Repo silmə əməliyyatı
if (isset($_GET['delete_repo'])) {
    $repoName = $_GET['delete_repo'];
    if (removeHelmRepo($repoName)) {
        $deleteMessage = "Repo '{$repoName}' uğurla silindi!";
        $deleteMessageType = 'success';
    } else {
        $deleteMessage = "Repo '{$repoName}' silinərkən xəta baş verdi!";
        $deleteMessageType = 'error';
    }
}

try {
    // Repo siyahısını oxumaq
    $repos = [];
    if (file_exists(REPOS_FILE)) {
        $reposData = file_get_contents(REPOS_FILE);
        if ($reposData !== false) {
            $repos = json_decode($reposData, true) ?: [];
        }
    }

    // Əsas repo siyahısını yeniləmək
    if (empty($repos)) {
        $result = runHelmCommand('repo list');
        if ($result['success']) {
            foreach ($result['output'] as $line) {
                if (strpos($line, 'NAME') === 0 || trim($line) === '') continue;
                
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 2) {
                    $repos[] = [
                        'name' => $parts[0],
                        'url' => $parts[1]
                    ];
                }
            }
            file_put_contents(REPOS_FILE, json_encode($repos));
        }
    }
} catch (Exception $e) {
    error_log("Index.php xətası: " . $e->getMessage());
    $repos = [];
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helm Repo Manager</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Helm Repo Manager</h1>
            <p>Helm chart repolarını idarə etmək üçün veb interfeys</p>
        </header>

        <?php if (isset($deleteMessage)): ?>
            <div class="alert alert-<?= $deleteMessageType ?>">
                <?= htmlspecialchars($deleteMessage) ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="add-repo.php" class="btn btn-primary">Yeni Repo Əlavə Et</a>
            <a href="search.php" class="btn btn-secondary">Chart Axtar</a>
        </div>

        <div class="repos-section">
            <h2>Mövcud Repolar</h2>
            
            <?php if (empty($repos)): ?>
                <div class="alert alert-info">
                    Heç bir repo tapılmadı. İlk repo-nu əlavə edin.
                </div>
            <?php else: ?>
                <div class="repos-grid">
                    <?php foreach ($repos as $repo): ?>
                        <div class="repo-card">
                            <h3><?= htmlspecialchars($repo['name'] ?? '') ?></h3>
                            <p class="repo-url"><?= htmlspecialchars($repo['url'] ?? '') ?></p>
                            <div class="repo-actions">
                                <a href="list-charts.php?repo=<?= urlencode($repo['name'] ?? '') ?>" 
                                   class="btn btn-small btn-primary">Chart-ları Gör</a>
                                <a href="index.php?delete_repo=<?= urlencode($repo['name'] ?? '') ?>" 
                                   class="btn btn-small btn-danger" 
                                   onclick="return confirm('<?= htmlspecialchars($repo['name'] ?? '') ?> repo-sunu silmək istədiyinizə əminsiniz?')">Sil</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="system-info">
            <h2>Sistem Məlumatı</h2>
            <?php
            try {
                $version = runHelmCommand('version');
                if ($version['success']): ?>
                    <pre class="system-output"><?= htmlspecialchars(implode("\n", $version['output'])) ?></pre>
                <?php else: ?>
                    <div class="alert alert-error">
                        Helm tapılmadı və ya icra oluna bilmir.<br>
                        Xəta: <?= htmlspecialchars(implode(', ', $version['output'])) ?>
                    </div>
                <?php endif;
            } catch (Exception $e) {
                echo '<div class="alert alert-error">Xəta: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>
    </div>
</body>
</html>
