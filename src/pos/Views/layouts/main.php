<?php
/**
 * TopTea POS - Main Layout
 *
 * This is a temporary placeholder. The full POS interface from
 * store/store_html/html/pos/index.php should be migrated here.
 *
 * @author TopTea Engineering Team
 * @version 1.0.0 (Placeholder)
 * @date 2026-01-03
 */

use TopTea\POS\Auth\AuthGuard;

$user = AuthGuard::user();
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>TopTea · POS 点餐台</title>

  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Configuration -->
  <script>
    window.SHIFT_POLICY = 'force_all';
    window.CSRF_TOKEN = '<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    window.CACHE_VERSION = '<?php echo $cache_version ?? time(); ?>';
  </script>
</head>
<body>

  <div class="container mt-5">
    <div class="alert alert-info">
      <h4><i class="bi bi-info-circle"></i> POS架构重构进行中</h4>
      <p>
        新的POS架构已就绪！完整的POS界面正在从旧位置
        (<code>store/store_html/html/pos/</code>)
        迁移到新的标准化架构。
      </p>
      <hr>
      <p class="mb-0">
        <strong>当前用户:</strong> <?php echo htmlspecialchars($user['display_name'] ?? 'Unknown'); ?><br>
        <strong>门店:</strong> <?php echo htmlspecialchars($user['store_name'] ?? 'Unknown'); ?><br>
        <strong>角色:</strong> <?php echo htmlspecialchars($user['role'] ?? 'Unknown'); ?><br>
        <strong>班次ID:</strong> <?php echo $user['shift_id'] ?? '未开班'; ?>
      </p>
      <hr>
      <p><strong>临时访问:</strong> 在迁移完成前，您可以继续使用旧版POS界面：<br>
        <a href="/store/store_html/html/pos/index.php" class="btn btn-primary mt-2">
          <i class="bi bi-arrow-right"></i> 访问旧版POS (临时)
        </a>
        <a href="logout.php" class="btn btn-outline-danger mt-2">
          <i class="bi bi-box-arrow-right"></i> 退出登录
        </a>
      </p>
    </div>

    <div class="card">
      <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-check-circle"></i> 架构重构已完成</h5>
      </div>
      <div class="card-body">
        <h6>✅ 已完成的架构改进：</h6>
        <ul>
          <li><code>public/pos/</code> - 公开访问入口（与KDS对等）</li>
          <li><code>src/pos/</code> - 核心代码目录（与KDS对等）</li>
          <li><code>TopTea\POS\*</code> - PSR-4命名空间</li>
          <li><code>Autoloader</code> - 自动类加载</li>
          <li><code>src/pos/Config/config.php</code> - 统一配置入口</li>
          <li><code>AuthGuard</code> - 认证守卫</li>
          <li><code>SessionManager</code> - 增强的Session管理</li>
        </ul>

        <h6 class="mt-4">📋 待完成：</h6>
        <ul>
          <li>将完整POS界面迁移到 <code>src/pos/Views/</code></li>
          <li>将JavaScript资源移至 <code>public/pos/assets/js/</code></li>
          <li>将CSS资源移至 <code>public/pos/assets/css/</code></li>
          <li>更新所有路径引用</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- JavaScript -->
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
