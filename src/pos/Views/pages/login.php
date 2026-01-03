<?php
/**
 * TopTea POS - Login Page View
 *
 * This is a temporary placeholder. The full login interface from
 * store/store_html/pos_backend/views/login_view.php should be migrated here.
 *
 * @author TopTea Engineering Team
 * @version 1.0.0 (Placeholder)
 * @date 2026-01-03
 */
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>登录 - TopTea POS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

  <div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
      <div class="col-md-5">

        <div class="card shadow">
          <div class="card-body p-5">
            <div class="text-center mb-4">
              <h2 class="fw-bold">TopTea POS</h2>
              <p class="text-muted">门店点餐系统</p>
            </div>

            <div class="alert alert-info">
              <h6><i class="bi bi-info-circle"></i> 架构重构通知</h6>
              <p class="small mb-0">
                新的POS架构已就绪！<br>
                临时请使用旧版登录：<br>
                <a href="/store/store_html/html/pos/login.php" class="btn btn-sm btn-primary mt-2 w-100">
                  <i class="bi bi-arrow-right"></i> 访问旧版登录 (临时)
                </a>
              </p>
            </div>

            <!-- Placeholder form (not functional yet) -->
            <form action="api/login_handler.php" method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">

              <div class="mb-3">
                <label for="store_code" class="form-label">门店代码</label>
                <input type="text" class="form-control" id="store_code" name="store_code" required disabled>
              </div>

              <div class="mb-3">
                <label for="username" class="form-label">用户名</label>
                <input type="text" class="form-control" id="username" name="username" required disabled>
              </div>

              <div class="mb-3">
                <label for="password" class="form-label">密码</label>
                <input type="password" class="form-control" id="password" name="password" required disabled>
              </div>

              <button type="submit" class="btn btn-primary w-100" disabled>
                <i class="bi bi-box-arrow-in-right"></i> 登录 (迁移中)
              </button>
            </form>

            <div class="text-center mt-4">
              <small class="text-muted">
                TopTea © 2026 | 版本 2.0 (架构重构)
              </small>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
