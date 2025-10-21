<?php
require_once 'config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repoName = trim($_POST['repo_name'] ?? '');
    $repoUrl = trim($_POST['repo_url'] ?? '');
    
    if (empty($repoName) || empty($repoUrl)) {
        $message = 'Repo adı və URL boş ola bilməz!';
        $messageType = 'error';
    } else {
        // Repo əlavə et
        if (addHelmRepo($repoName, $repoUrl)) {
            $message = 'Repo uğurla əlavə edildi!';
            $messageType = 'success';
            
            // Repo siyahısını yenilə
            $repos = [];
            if (file_exists(REPOS_FILE)) {
                $reposData = file_get_contents(REPOS_FILE);
                if ($reposData !== false) {
                    $repos = json_decode($reposData, true) ?: [];
                }
            }
            
            // Yeni repo əlavə et
            $repos[] = ['name' => $repoName, 'url' => $repoUrl];
            file_put_contents(REPOS_FILE, json_encode($repos));
            
            // Cache-i təmizlə
            $cacheFiles = glob(CACHE_DIR . "/*.cache");
            if ($cacheFiles !== false) {
                foreach ($cacheFiles as $cacheFile) {
                    unlink($cacheFile);
                }
            }
            
        } else {
            $message = 'Repo əlavə edilərkən xəta baş verdi. ';
            $messageType = 'error';
            
            // Əlavə xəta məlumatı
            $result = runHelmCommand("repo add {$repoName} {$repoUrl}");
            if (!$result['success']) {
                $message .= 'Xəta: ' . implode(', ', $result['output']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repo Əlavə Et - Helm Repo Manager</title>
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Yeni Repo Əlavə Et</h1>
            <a href="index.php" class="btn btn-secondary">Geri</a>
        </header>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" class="repo-form">
                <div class="form-group">
                    <label for="repo_name">Repo Adı:</label>
                    <input type="text" id="repo_name" name="repo_name" required 
                           placeholder="məs: mygov, digitalgovernment">
                </div>
                
                <div class="form-group">
                    <label for="repo_url">Repo URL:</label>
                    <input type="url" id="repo_url" name="repo_url" required 
                           placeholder="məs: https://charts.bitnami.com/bitnami">
                    <small>Qeyd: Bu repo-ya şəbəkə çıxışınızın olduğundan əmin olun.</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Repo Əlavə Et</button>
                    <a href="index.php" class="btn btn-secondary">Ləğv Et</a>
                </div>
            </form>
        </div>

        <div class="common-repos">
            <h3>Test üçün əlçatan Repolar:</h3>
            <ul>
                <li><strong>Bitnami:</strong> https://charts.bitnami.com/bitnami</li>
                <li><strong>Stable:</strong> https://charts.helm.sh/stable</li>
                <li><strong>Prometheus:</strong> https://prometheus-community.github.io/helm-charts</li>
            </ul>
        </div>

        <div class="debug-info">
            <h3>Texniki Məlumat:</h3>
            <p><strong>Helm Cache:</strong> <?= file_exists(HELM_CACHE_DIR) ? realpath(HELM_CACHE_DIR) : 'YOX' ?></p>
            <p><strong>Helm Config:</strong> <?= file_exists(HELM_CONFIG_DIR) ? realpath(HELM_CONFIG_DIR) : 'YOX' ?></p>
            <p><strong>Repositories File:</strong> <?= file_exists(HELM_REPOS_FILE) ? 'Mövcud' : 'YOX' ?></p>
        </div>
    </div>
</body>
</html>
