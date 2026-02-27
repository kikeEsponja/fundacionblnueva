<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/db.php';
$row = $pdo->query("SELECT password_hash, theme, failed_attempts, lock_until, profile_photo FROM admin_config WHERE id=1")->fetch();
$hash = $row ? ($row['password_hash'] ?? '') : '';
$theme = $row && !empty($row['theme']) ? $row['theme'] : 'light';
$failedAttempts = (int)($row['failed_attempts'] ?? 0);
$lockUntilRaw = $row['lock_until'] ?? null;
$lockUntilTs = $lockUntilRaw ? strtotime($lockUntilRaw) : 0;
$error = '';
$notice = '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$csrf = $_SESSION['csrf'] ?? '';
$cssFile = realpath(__DIR__ . '/../recursos/estilos/estilos.css');
$cssV = $cssFile && file_exists($cssFile) ? filemtime($cssFile) : time();
$lockRemaining = $lockUntilTs > time() ? ($lockUntilTs - time()) : 0;
// Detect whether the app is installed at domain root or inside a subfolder.
// On production (lovestoblog.com) the site lives at /, so basePath = ''.
// On local XAMPP it lives at /sistema-fundacion/, so basePath = '/sistema-fundacion'.
$projectName = basename(dirname(__DIR__));
$knownAppDirs = ['admin', 'recursos', 'assets', 'static', 'public'];
$firstSegment = explode('/', ltrim($_SERVER['REQUEST_URI'] ?? '', '/'))[0];
$firstSegment = strtok($firstSegment, '?'); // strip query
$basePath = in_array(strtolower($firstSegment), $knownAppDirs, true) ? '' : '/' . $projectName;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tok = $_POST['csrf'] ?? '';
  if (!$tok || !hash_equals($csrf, $tok)) {
    $error = 'Sesión expirada. Vuelva a intentar.';
  } else
  if (isset($_POST['setup_password'])) {
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['password_confirm'] ?? '';
    if ($p1 !== '' && $p1 === $p2) {
      $newHash = password_hash($p1, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE admin_config SET password_hash=? WHERE id=1");
      $stmt->execute([$newHash]);
      session_regenerate_id(true);
      $_SESSION['admin_logged'] = true;
      header('Location: index.php');
      exit;
    } else {
      $error = 'Contraseña inválida o no coincide';
    }
  } elseif (isset($_POST['login'])) {
    $p = $_POST['password'] ?? '';
    $now = time();
    if ($lockUntilTs && $now < $lockUntilTs) {
      $mins = ceil(($lockUntilTs - $now)/60);
      $error = 'Cuenta bloqueada temporalmente. Intente en ~'.$mins.' min.';
    } elseif ($hash && password_verify($p, $hash)) {
      session_regenerate_id(true);
      $_SESSION['admin_logged'] = true;
      $_SESSION['admin_last'] = time();
      $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
      $_SESSION['admin_ua'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);
      $stmt = $pdo->prepare("UPDATE admin_config SET failed_attempts=0, lock_until=NULL WHERE id=1");
      $stmt->execute();
      header('Location: index.php');
      exit;
    } else {
      $failedAttempts++;
      if ($failedAttempts >= 5) {
        $lockUntil = date('Y-m-d H:i:s', time() + 15*60);
        $stmt = $pdo->prepare("UPDATE admin_config SET failed_attempts=0, lock_until=? WHERE id=1");
        $stmt->execute([$lockUntil]);
        $error = 'Demasiados intentos fallidos. Bloqueado por 15 minutos.';
      } else {
        $stmt = $pdo->prepare("UPDATE admin_config SET failed_attempts=? WHERE id=1");
        $stmt->execute([$failedAttempts]);
        $error = 'Credenciales inválidas';
      }
    }
  } elseif (isset($_POST['logout'])) {
    session_regenerate_id(true);
    session_destroy();
    header('Location: index.php');
    exit;
  } elseif (isset($_POST['set_theme'])) {
    $t = $_POST['theme'] ?? 'light';
    $t = $t === 'dark' ? 'dark' : 'light';
    $stmt = $pdo->prepare("UPDATE admin_config SET theme=? WHERE id=1");
    $stmt->execute([$t]);
    $_SESSION['flash'] = ['type'=>'success','text'=> $t === 'dark' ? 'Modo oscuro activado' : 'Modo claro activado'];
    $hash = $_POST['return_hash'] ?? '';
    $allowed = ['#subir-medios','#gestionar-imagenes','#gestionar-videos','#gestionar-publicaciones','#nueva-publicacion','#preferencias','#perfil'];
    if (!in_array($hash, $allowed, true)) { $hash = '#preferencias'; }
    header('Location: index.php'.$hash);
    exit;
  } elseif (isset($_POST['change_password'])) {
    $curr = $_POST['current_password'] ?? '';
    $p1 = $_POST['new_password'] ?? '';
    $p2 = $_POST['new_password_confirm'] ?? '';
    if (!$hash || !password_verify($curr, $hash)) {
      $error = 'La contraseña actual no es correcta.';
    } elseif ($p1 === '' || $p1 !== $p2) {
      $error = 'La nueva contraseña es inválida o no coincide.';
    } else {
      $newHash = password_hash($p1, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE admin_config SET password_hash=? WHERE id=1");
      $stmt->execute([$newHash]);
      session_regenerate_id(true);
      $_SESSION['admin_logged'] = true;
      $_SESSION['flash'] = ['type'=>'success','text'=>'Contraseña actualizada'];
      $hash = $_POST['return_hash'] ?? '';
      $allowed = ['#subir-medios','#gestionar-imagenes','#gestionar-videos','#gestionar-publicaciones','#nueva-publicacion','#preferencias','#perfil'];
      if (!in_array($hash, $allowed, true)) { $hash = '#perfil'; }
      header('Location: index.php'.$hash);
      exit;
    }
  }
}
$logged = isLoggedIn();
if ($logged) {
  $perImg = 20; $perVid = 20; $perPost = 20;
  $pi = max(1, (int)($_GET['pi'] ?? 1));
  $pv = max(1, (int)($_GET['pv'] ?? 1));
  $pp = max(1, (int)($_GET['pp'] ?? 1));
  $totalImages = (int)$pdo->query("SELECT COUNT(*) FROM gallery_images")->fetchColumn();
  $totalVideos = (int)$pdo->query("SELECT COUNT(*) FROM gallery_videos")->fetchColumn();
  $totalPosts = (int)$pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
  $imgPages = max(1, (int)ceil($totalImages / $perImg));
  $vidPages = max(1, (int)ceil($totalVideos / $perVid));
  $postPages = max(1, (int)ceil($totalPosts / $perPost));
  if ($pi > $imgPages) $pi = $imgPages;
  if ($pv > $vidPages) $pv = $vidPages;
  if ($pp > $postPages) $pp = $postPages;
  $imgOffset = ($pi - 1) * $perImg;
  $vidOffset = ($pv - 1) * $perVid;
  $postOffset = ($pp - 1) * $perPost;

  $stmtI = $pdo->prepare("SELECT id, src, thumb, alt, date FROM gallery_images ORDER BY date DESC LIMIT :limit OFFSET :offset");
  $stmtI->bindValue(':limit', $perImg, PDO::PARAM_INT);
  $stmtI->bindValue(':offset', $imgOffset, PDO::PARAM_INT);
  $stmtI->execute();
  $images = $stmtI->fetchAll();

  $stmtV = $pdo->prepare("SELECT id, src, date FROM gallery_videos ORDER BY date DESC LIMIT :limit OFFSET :offset");
  $stmtV->bindValue(':limit', $perVid, PDO::PARAM_INT);
  $stmtV->bindValue(':offset', $vidOffset, PDO::PARAM_INT);
  $stmtV->execute();
  $videos = $stmtV->fetchAll();

  $stmtP = $pdo->prepare("SELECT id, title, slug, date FROM posts ORDER BY date DESC LIMIT :limit OFFSET :offset");
  $stmtP->bindValue(':limit', $perPost, PDO::PARAM_INT);
  $stmtP->bindValue(':offset', $postOffset, PDO::PARAM_INT);
  $stmtP->execute();
  $posts = $stmtP->fetchAll();
}
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin</title><link rel="icon" href="../recursos/imagenes/logo/ico_logo.ico" type="image/x-icon"><link rel="stylesheet" href="../recursos/estilos/estilos.css?v=<?php echo $cssV; ?>"></head><body class="admin <?php echo $theme === 'dark' ? 'admin-dark' : ''; ?>"><main class="admin-main">
<?php if (!$hash): ?>
<div class="admin-center">
  <div class="admin-card admin-form">
    <img src="../recursos/imagenes/logo/logo_preview.png" alt="Logo Fundación Betty Linares" class="admin-logo admin-logo-center">
    <h2>Configurar Acceso</h2>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="password" name="password" placeholder="Nueva contraseña" required>
      <input type="password" name="password_confirm" placeholder="Confirmar contraseña" required>
      <div class="admin-actions">
        <button class="btn btn-primary" name="setup_password" value="1" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>
<?php elseif (!$logged): ?>
<div class="admin-center">
  <div class="admin-card admin-form">
    <img src="../recursos/imagenes/logo/logo_preview.png" alt="Logo Fundación Betty Linares" class="admin-logo admin-logo-center">
    <h2>Ingresar</h2>
    <?php if ($lockRemaining > 0): ?>
      <div class="admin-banner" id="lock-banner">Cuenta bloqueada temporalmente. Intente de nuevo en <span id="lock-remaining"></span>.</div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="password" name="password" placeholder="Contraseña" required>
      <div class="admin-actions">
        <button class="btn btn-primary" id="login-submit" name="login" value="1" type="submit">Entrar</button>
      </div>
    </form>
  </div>
</div>
<?php else: ?>
<div class="admin-header">
  <div class="admin-brand">
    <img src="../recursos/imagenes/logo/logo_preview.png" alt="Logo Fundación Betty Linares" class="admin-logo">
    <h2>Panel de Administración</h2>
  </div>
  <div class="admin-header-right">
    <div class="admin-quick-links">
      <a class="btn btn-secondary" href="../recursos/vistas/galeria.html" target="_blank" rel="noopener noreferrer">Ver Galería</a>
      <a class="btn btn-secondary" href="../recursos/vistas/blog.html" target="_blank" rel="noopener noreferrer">Ver Blog</a>
    </div>
    <?php $avatar = $row['profile_photo'] ?? null; $avatarUrl = $avatar ? preg_replace('#^\.\./imagenes/#','../recursos/imagenes/',$avatar) : null; if ($avatarUrl): ?>
      <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de perfil" class="admin-avatar-sm">
    <?php else: ?>
      <div class="admin-avatar-sm admin-avatar-placeholder">A</div>
    <?php endif; ?>
    <form method="post" id="logout-form">
      <input type="hidden" name="logout" value="1">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <button class="btn btn-secondary" id="logout-btn" type="button">Salir</button>
    </form>
  </div>
</div>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a class="nav-item active" href="#subir-medios">Subir Medios</a>
      <a class="nav-item" href="#nueva-publicacion">Nueva Publicación</a>
      <a class="nav-item" href="#gestionar-imagenes">Gestionar Imágenes</a>
      <a class="nav-item" href="#gestionar-videos">Gestionar Videos</a>
      <a class="nav-item" href="#gestionar-publicaciones">Gestionar Publicaciones</a>
      <a class="nav-item" href="#preferencias">Preferencias</a>
      <a class="nav-item" href="#perfil">Perfil</a>
    </nav>
  </aside>
  <div class="admin-content">
  <div class="admin-grid">
  <section class="admin-card admin-form pane" id="preferencias">
    <h3>Preferencias del Panel</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#preferencias">
      <label style="display:flex;align-items:center;gap:.5rem;margin:.25rem 0;">
        <input type="radio" name="theme" value="light" <?php echo $theme === 'light' ? 'checked' : ''; ?>> Modo Claro
      </label>
      <label style="display:flex;align-items:center;gap:.5rem;margin:.25rem 0;">
        <input type="radio" name="theme" value="dark" <?php echo $theme === 'dark' ? 'checked' : ''; ?>> Modo Oscuro
      </label>
      <div class="admin-actions center">
        <button class="btn btn-primary" name="set_theme" value="1" type="submit">Guardar Preferencia</button>
      </div>
    </form>
  </section>
  <section class="admin-card admin-form pane" id="perfil">
    <h3>Perfil de Administrador</h3>
    <div style="display:flex;align-items:center;gap:1rem;margin:.5rem 0;">
      <?php $avatar = $row['profile_photo'] ?? null; $avatarUrl = $avatar ? preg_replace('#^\.\./imagenes/#','../recursos/imagenes/',$avatar) : null; if ($avatarUrl): ?>
        <img src="<?php echo htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Foto de perfil" class="admin-avatar">
      <?php else: ?>
        <div class="admin-avatar admin-avatar-placeholder">A</div>
      <?php endif; ?>
    </div>
    <form action="upload_profile.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#perfil">
      <div class="file-input">
        <span class="file-button">Seleccionar foto</span>
        <span class="file-name" id="file-profile-name">Ningún archivo seleccionado</span>
        <input type="file" id="file-profile" name="profile" accept="image/*" required>
      </div>
      <div class="admin-actions center">
        <button class="btn btn-primary" type="submit">Actualizar Foto</button>
      </div>
    </form>
    <?php if (!empty($row['profile_photo'])): ?>
    <form action="delete_profile_photo.php" method="post" style="margin-top:.5rem;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#perfil">
      <button class="btn btn-secondary" type="submit">Eliminar foto de perfil</button>
    </form>
    <?php endif; ?>
    <hr style="margin:1rem 0;border:none;border-top:1px solid #e2e8f0;">
    <h3 style="margin-top:.5rem;">Cambiar Contraseña</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#perfil">
      <input type="password" name="current_password" placeholder="Contraseña actual" required>
      <input type="password" name="new_password" placeholder="Nueva contraseña" required>
      <input type="password" name="new_password_confirm" placeholder="Confirmar nueva contraseña" required>
      <div class="admin-actions center">
        <button class="btn btn-primary" name="change_password" value="1" type="submit">Actualizar</button>
      </div>
    </form>
  </section>
  <section class="admin-card admin-form pane" id="subir-imagen">
    <h3>Subir Imagen</h3>
    <form action="upload_image.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#subir-medios">
      <div class="file-input">
        <span class="file-button">Seleccionar imagen</span>
        <span class="file-name" id="file-image-name">Ningún archivo seleccionado</span>
        <input type="file" id="file-image" name="image" accept="image/*" required>
      </div>
      <input type="text" name="alt" placeholder="Texto alternativo">
      <label><input type="checkbox" name="featured" value="1"> Destacar</label>
      <div class="admin-actions center">
        <button class="btn btn-primary" type="submit">Subir</button>
      </div>
    </form>
  </section>
  <section class="admin-card admin-form pane" id="subir-video">
    <h3>Subir Video</h3>
    <form action="upload_video.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#subir-medios">
      <div class="file-input">
        <span class="file-button">Seleccionar video</span>
        <span class="file-name" id="file-video-name">Ningún archivo seleccionado</span>
        <input type="file" id="file-video" name="video" accept="video/mp4" required>
      </div>
      <label><input type="checkbox" name="featured" value="1"> Destacar</label>
      <div class="admin-actions center">
        <button class="btn btn-primary" type="submit">Subir</button>
      </div>
    </form>
  </section>
  <section class="admin-card admin-form admin-card-wide pane" id="nueva-publicacion">
    <h3>Nueva Publicación</h3>
    <form action="create_post.php" method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#nueva-publicacion">
      <input type="text" name="title" placeholder="Título" required>
      <input type="text" id="post-slug" name="slug" placeholder="Slug (ej. ayuda-comunitaria)" required>
      <div class="slug-preview"><strong id="slug-preview-text"></strong></div>
      <textarea name="excerpt" placeholder="Resumen" rows="3"></textarea>
      <textarea name="content" placeholder="Contenido" rows="6"></textarea>
      <label><input type="checkbox" name="featured" value="1"> Destacar</label>
      <div class="admin-actions center">
        <button class="btn btn-primary" type="submit">Publicar</button>
      </div>
    </form>
  </section>
  <section class="admin-card admin-form pane" id="gestionar-imagenes">
    <h3>Gestionar Imágenes</h3>
    <form action="delete_image.php" method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#gestionar-imagenes">
      <input type="text" placeholder="Filtrar imágenes..." id="filter-images" style="width:100%;margin:.5rem 0;">
      <div class="admin-table-wrap">
        <table class="admin-table" id="table-images">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all-images"></th>
              <th>Miniatura</th>
              <th>Alt</th>
              <th>Fecha</th>
              <th>Ver</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($images as $img): ?>
              <?php
                $t = $img['thumb'] ?: '';
                $s = $img['src'] ?? '';
                $tUrl = $t ? preg_replace('#^\.\./imagenes/#','../recursos/imagenes/',$t) : '';
                $sUrl = $s ? preg_replace('#^\.\./imagenes/#','../recursos/imagenes/',$s) : '';
              ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$img['id']; ?>"></td>
                <td><?php if ($tUrl): ?><img class="admin-thumb" src="<?php echo htmlspecialchars($tUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($img['alt'] ?: 'Imagen', ENT_QUOTES, 'UTF-8'); ?>"><?php endif; ?></td>
                <td><?php echo htmlspecialchars($img['alt'] ?: 'Imagen', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo date('Y-m-d', strtotime($img['date'])); ?></td>
                <td class="table-actions"><?php if ($sUrl): ?><a href="<?php echo htmlspecialchars($sUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="padding:.25rem .5rem;min-height:28px;font-size:.8rem;">Ver</a><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="admin-actions">
        <div style="margin-right:auto;">
          <span>Página <?php echo $pi; ?> de <?php echo $imgPages; ?> (<?php echo $totalImages; ?>)</span>
        </div>
        <div>
          <?php if ($pi > 1): ?><a class="btn btn-secondary" href="index.php?pi=<?php echo $pi-1; ?>&pv=<?php echo $pv; ?>&pp=<?php echo $pp; ?>#gestionar-imagenes">Anterior</a><?php endif; ?>
          <?php if ($pi < $imgPages): ?><a class="btn btn-secondary" href="index.php?pi=<?php echo $pi+1; ?>&pv=<?php echo $pv; ?>&pp=<?php echo $pp; ?>#gestionar-imagenes">Siguiente</a><?php endif; ?>
        </div>
        <button class="btn btn-secondary" name="delete_all" value="1" type="submit">Eliminar Todos</button>
        <button class="btn btn-primary" type="submit">Eliminar Seleccionados</button>
      </div>
    </form>
  </section>
  <section class="admin-card admin-form pane" id="gestionar-videos">
    <h3>Gestionar Videos</h3>
    <form action="delete_video.php" method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#gestionar-videos">
      <input type="text" placeholder="Filtrar videos..." id="filter-videos" style="width:100%;margin:.5rem 0;">
      <div class="admin-table-wrap">
        <table class="admin-table" id="table-videos">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all-videos"></th>
              <th>Archivo</th>
              <th>Preview</th>
              <th>Fecha</th>
              <th>Ver</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($videos as $v): ?>
              <?php $vUrl = preg_replace('#^\.\./videos/#',$basePath.'/recursos/videos/',$v['src']); $vName = basename($v['src']); ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$v['id']; ?>"></td>
                <td><?php echo htmlspecialchars($vName, ENT_QUOTES, 'UTF-8'); ?></td>
                <td><video class="admin-video-thumb" src="<?php echo htmlspecialchars($vUrl, ENT_QUOTES, 'UTF-8'); ?>" muted playsinline preload="metadata"></video></td>
                <td><?php echo date('Y-m-d', strtotime($v['date'])); ?></td>
                <td class="table-actions"><a href="<?php echo htmlspecialchars($vUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="padding:.25rem .5rem;min-height:28px;font-size:.8rem;">Ver</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="admin-actions">
        <div style="margin-right:auto;">
          <span>Página <?php echo $pv; ?> de <?php echo $vidPages; ?> (<?php echo $totalVideos; ?>)</span>
        </div>
        <div>
          <?php if ($pv > 1): ?><a class="btn btn-secondary" href="index.php?pi=<?php echo $pi; ?>&pv=<?php echo $pv-1; ?>&pp=<?php echo $pp; ?>#gestionar-videos">Anterior</a><?php endif; ?>
          <?php if ($pv < $vidPages): ?><a class="btn btn-secondary" href="index.php?pi=<?php echo $pi; ?>&pv=<?php echo $pv+1; ?>&pp=<?php echo $pp; ?>#gestionar-videos">Siguiente</a><?php endif; ?>
        </div>
        <button class="btn btn-secondary" name="delete_all" value="1" type="submit">Eliminar Todos</button>
        <button class="btn btn-primary" type="submit">Eliminar Seleccionados</button>
      </div>
    </form>
  </section>
  <section class="admin-card admin-form pane" id="gestionar-publicaciones">
    <h3>Gestionar Publicaciones</h3>
    <form action="delete_post.php" method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="return_hash" value="#gestionar-publicaciones">
      <input type="text" placeholder="Filtrar publicaciones..." id="filter-posts" style="width:100%;margin:.5rem 0;">
      <div class="admin-table-wrap">
        <table class="admin-table" id="table-posts">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all-posts"></th>
              <th>Título</th>
              <th>Slug</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts as $p): ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$p['id']; ?>"></td>
                <td><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo date('Y-m-d', strtotime($p['date'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="admin-actions">
        <div style="margin-right:auto;">
          <span>Página <?php echo $pp; ?> de <?php echo $postPages; ?> (<?php echo $totalPosts; ?>)</span>
        </div>
        <div>
          <?php if ($pp > 1): ?><a class="btn btn-secondary" href="index.php?pi=<?php echo $pi; ?>&pv=<?php echo $pv; ?>&pp=<?php echo $pp-1; ?>#gestionar-publicaciones">Anterior</a><?php endif; ?>
          <?php if ($pp < $postPages): ?><a class="btn btn-secondary" href="index.php?pi=<?php echo $pi; ?>&pv=<?php echo $pv; ?>&pp=<?php echo $pp+1; ?>#gestionar-publicaciones">Siguiente</a><?php endif; ?>
        </div>
        <button class="btn btn-secondary" name="delete_all" value="1" type="submit">Eliminar Todos</button>
        <button class="btn btn-primary" type="submit">Eliminar Seleccionados</button>
      </div>
    </form>
  </section>
</div></div></div>
<?php endif; ?>
</main>
<div class="modal" id="logout-modal" aria-hidden="true" role="dialog">
  <div class="modal-backdrop"></div>
  <div class="modal-content">
    <div class="modal-header">Cerrar sesión</div>
    <div class="modal-body">¿Seguro que deseas salir del panel de administración?</div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="cancel-logout">Cancelar</button>
      <button class="btn btn-primary" id="confirm-logout">Cerrar sesión</button>
    </div>
  </div>
</div>
<div class="modal" id="message-modal" aria-hidden="true" role="dialog">
  <div class="modal-backdrop"></div>
  <div class="modal-content">
    <div class="modal-header" id="message-title">Aviso</div>
    <div class="modal-body" id="message-text"></div>
    <div class="modal-actions">
      <button class="btn btn-primary" id="message-ok">Entendido</button>
    </div>
  </div>
  </div>
<div class="modal" id="confirm-delete-all" aria-hidden="true" role="dialog">
  <div class="modal-backdrop"></div>
  <div class="modal-content">
    <div class="modal-header">Confirmar eliminación</div>
    <div class="modal-body">Esto eliminará todos los elementos listados. ¿Deseas continuar?</div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="cancel-delete-all">Cancelar</button>
      <button class="btn btn-primary" id="confirm-delete-all-btn">Eliminar</button>
    </div>
  </div>
</div>
<script>
(function(){
  const form = document.getElementById('logout-form');
  const btn = document.getElementById('logout-btn');
  if (form && btn) {
    const modal = document.getElementById('logout-modal');
    const cancelBtn = document.getElementById('cancel-logout');
    const confirmBtn = document.getElementById('confirm-logout');
    function openModal(e){
      e.preventDefault();
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden','false');
    }
    function closeModal(){
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden','true');
    }
    btn.addEventListener('click', openModal);
    cancelBtn?.addEventListener('click', function(e){ e.preventDefault(); closeModal(); });
    modal?.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    confirmBtn?.addEventListener('click', function(e){
      e.preventDefault();
      closeModal();
      form.submit();
    });
  }
})();
(function(){
  var modal = document.getElementById('confirm-delete-all');
  var cancelBtn = document.getElementById('cancel-delete-all');
  var confirmBtn = document.getElementById('confirm-delete-all-btn');
  var triggerForm = null;
  function open(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
  function close(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }
  var buttons = document.querySelectorAll('button[name="delete_all"]');
  buttons.forEach(function(b){
    b.addEventListener('click', function(e){
      e.preventDefault();
      triggerForm = b.closest('form');
      open();
    });
  });
  cancelBtn?.addEventListener('click', function(e){ e.preventDefault(); close(); triggerForm=null; });
  modal?.addEventListener('click', function(e){ if (e.target === modal) { close(); triggerForm=null; } });
  confirmBtn?.addEventListener('click', function(e){
    e.preventDefault();
    if (triggerForm) {
      var inp = triggerForm.querySelector('input[name="csrf"]');
      if (inp) triggerForm.submit();
    }
    close();
    triggerForm=null;
  });
  function setFilterTable(inputId, tableId){
    var input = document.getElementById(inputId);
    var table = document.getElementById(tableId);
    if (!input || !table) return;
    var tbody = table.querySelector('tbody');
    input.addEventListener('input', function(){
      var q = input.value.toLowerCase();
      Array.prototype.forEach.call(tbody.rows, function(row){
        var t = row.textContent.toLowerCase();
        row.style.display = t.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }
  setFilterTable('filter-images','table-images');
  setFilterTable('filter-videos','table-videos');
  setFilterTable('filter-posts','table-posts');
  function setupSelectAll(headerId, tableId){
    var headerCb = document.getElementById(headerId);
    var table = document.getElementById(tableId);
    if (!headerCb || !table) return;
    headerCb.addEventListener('change', function(){
      var cbs = table.querySelectorAll('tbody input[type="checkbox"][name="ids[]"]');
      cbs.forEach(function(cb){ cb.checked = headerCb.checked; });
    });
  }
  setupSelectAll('select-all-images','table-images');
  setupSelectAll('select-all-videos','table-videos');
  setupSelectAll('select-all-posts','table-posts');
})();
(function(){
  var flashMsg = <?php echo json_encode($flash ?: null, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  if (flashMsg) {
    // toast temporal (éxito/avisos)
    var d = document.createElement('div');
    d.className = 'status-message ' + (flashMsg.type === 'error' ? 'error' : 'success');
    d.id = 'admin-toast';
    d.setAttribute('role','status');
    d.textContent = flashMsg.text;
    d.style.position = 'fixed';
    d.style.top = '90px';
    d.style.left = '50%';
    d.style.transform = 'translateX(-50%)';
    d.style.zIndex = 10000;
    document.body.appendChild(d);
    setTimeout(function(){ d.classList.add('show'); }, 20);
    setTimeout(function(){
      d.classList.remove('show');
      setTimeout(function(){ d.remove(); }, 400);
    }, 4000);
  }
})();
(function(){
  var err = <?php echo json_encode($error ?: null, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  if (!err) return;
  var modal = document.getElementById('message-modal');
  var title = document.getElementById('message-title');
  var text = document.getElementById('message-text');
  var ok = document.getElementById('message-ok');
  title.textContent = 'Error';
  text.textContent = err;
  function open(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
  function close(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }
  ok.addEventListener('click', function(e){ e.preventDefault(); close(); });
  modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
  open();
})();
(function(){
  var bannerWrap = document.getElementById('lock-banner');
  var bannerSpan = document.getElementById('lock-remaining');
  var loginBtn = document.getElementById('login-submit');
  var remain = <?php echo (int)$lockRemaining; ?>;
  function fmt(s){
    var m = Math.floor(s/60), sec = s%60;
    return String(m).padStart(2,'0')+':'+String(sec).padStart(2,'0');
  }
  function tick(){
    if (remain <= 0) {
      if (bannerSpan) bannerSpan.textContent = '00:00';
      if (loginBtn) loginBtn.disabled = false;
      if (bannerWrap) {
        bannerWrap.classList.add('hide');
        setTimeout(function(){ bannerWrap && bannerWrap.remove(); }, 400);
      }
      return;
    }
    if (bannerSpan) bannerSpan.textContent = fmt(remain);
    if (loginBtn) loginBtn.disabled = true;
    remain--;
    setTimeout(tick, 1000);
  }
  if (bannerSpan) {
    // Auto-ocultar banner a los 6s aunque siga el bloqueo
    setTimeout(function(){ if (bannerWrap) { bannerWrap.classList.add('hide'); setTimeout(function(){ bannerWrap && bannerWrap.remove(); }, 400); } }, 6000);
    tick();
  }
(function(){
  var navItems = document.querySelectorAll('.admin-nav .nav-item');
  var panes = document.querySelectorAll('.pane');
  function clearActive(){
    panes.forEach(function(p){ p.classList.remove('active'); });
    navItems.forEach(function(a){ a.classList.remove('active'); });
  }
  function show(hash){
    clearActive();
    var target = (hash || '').replace('#','');
    if (!target || target === 'subir-medios') {
      var p1 = document.getElementById('subir-imagen');
      var p2 = document.getElementById('subir-video');
      if (p1) p1.classList.add('active');
      if (p2) p2.classList.add('active');
      var a = document.querySelector('.admin-nav .nav-item[href="#subir-medios"]');
      if (a) a.classList.add('active');
      return;
    }
    var pane = document.getElementById(target);
    if (pane) pane.classList.add('active');
    var link = document.querySelector('.admin-nav .nav-item[href="#'+target+'"]');
    if (link) link.classList.add('active');
  }
  navItems.forEach(function(a){
    a.addEventListener('click', function(e){
      e.preventDefault();
      var h = a.getAttribute('href');
      history.replaceState(null, '', h);
      show(h);
    });
  });
  show(location.hash || '#subir-medios');
})();
(function(){
  var imgInput = document.getElementById('file-image');
  var imgName = document.getElementById('file-image-name');
  if (imgInput && imgName) {
    imgInput.addEventListener('change', function(){
      var n = imgInput.files && imgInput.files.length ? imgInput.files[0].name : 'Ningún archivo seleccionado';
      imgName.textContent = n;
    });
  }
  var vidInput = document.getElementById('file-video');
  var vidName = document.getElementById('file-video-name');
  if (vidInput && vidName) {
    vidInput.addEventListener('change', function(){
      var n = vidInput.files && vidInput.files.length ? vidInput.files[0].name : 'Ningún archivo seleccionado';
      vidName.textContent = n;
    });
  }
  var proInput = document.getElementById('file-profile');
  var proName = document.getElementById('file-profile-name');
  if (proInput && proName) {
    proInput.addEventListener('change', function(){
      var n = proInput.files && proInput.files.length ? proInput.files[0].name : 'Ningún archivo seleccionado';
      proName.textContent = n;
    });
  }
  var slugInput = document.getElementById('post-slug');
  var slugPreview = document.getElementById('slug-preview-text');
  function normalizeSlug(s){
    return (s||'').toLowerCase().replace(/[^a-z0-9\-]+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
  }
  if (slugInput && slugPreview) {
    slugInput.addEventListener('input', function(){
      slugPreview.textContent = normalizeSlug(slugInput.value);
    });
    slugPreview.textContent = normalizeSlug(slugInput.value);
  }
})();
})();
</script>
