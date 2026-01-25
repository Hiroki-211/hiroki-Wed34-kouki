<?php
session_start();
if (empty($_SESSION['login_user_id'])) {  // 非ログインの場合利用不可
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  exit;
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// ログイン中のユーザー
$login_user_id = $_SESSION['login_user_id'];
// ログイン中のユーザー情報を取得
$user_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$user_sth->execute([':id' => $login_user_id]);
$user = $user_sth->fetch(PDO::FETCH_ASSOC);

// 投稿処理
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])){

  // 画像ファイル名を配列で管理（最大4枚）
  $image_filenames = [null, null, null, null];

  // 画像が送られてきた場合（JSON配列として）
  if (!empty($_POST['image_base64'])) {
    // JSON文字列をデコード
    $image_base64_array = json_decode($_POST['image_base64'], true);
    
    // 配列でない場合は単一の値として処理
    if (!is_array($image_base64_array)) {
      $image_base64_array = [$_POST['image_base64']];
    }

    // 最大4枚まで処理
    for ($i = 0; $i < min(4, count($image_base64_array)); $i++) {
      if (!empty($image_base64_array[$i])) {
        // 先頭の data:~base64, のところは削る
        $base64 = preg_replace('/^data:.+base64,/', '', $image_base64_array[$i]);

        // base64からバイナリにデコードする
        $image_binary = base64_decode($base64);

        // 新しいファイル名を決めてバイナリを出力する
        $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
        $filepath = '/var/www/upload/image/' . $image_filename;
        file_put_contents($filepath, $image_binary);

        $image_filenames[$i] = $image_filename;
      }
    }
  }

  // insertする（4つの画像カラムに対応）
  $insert_sth = $dbh->prepare("INSERT INTO posts (user_id, content, image_filename1, image_filename2, image_filename3, image_filename4) VALUES (:user_id, :content, :image_filename1, :image_filename2, :image_filename3, :image_filename4)");
  $insert_sth->execute([
    ':user_id' => $_SESSION['login_user_id'],
    ':content' => $_POST['body'],
    ':image_filename1' => $image_filenames[0],
    ':image_filename2' => $image_filenames[1],
    ':image_filename3' => $image_filenames[2],
    ':image_filename4' => $image_filenames[3],
  ]);

  // 処理が終わったらリダイレクトする
  // リダイレクトしないと、リロード時にまた同じ内容でPOSTすることになる
  header("HTTP/1.1 303 See Other");
  header("Location: ./timeline.php");
  exit;
}

// LIMITとOFFSETを指定
$limit = max(5, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

// 投稿データを取得
// フォローしている人の投稿と自分自身の投稿のみ表示
$sql = 'SELECT posts.*, users.username AS user_name, users.icon_filename AS user_icon_filename'
    . ' FROM posts'
    . ' INNER JOIN users ON posts.user_id = users.id'
    . ' LEFT OUTER JOIN user_relationships ON posts.user_id = user_relationships.followee_user_id'
    . ' WHERE user_relationships.follower_user_id = :login_user_id OR posts.user_id = :login_user_id'
    . ' ORDER BY posts.created_at DESC'
    . ' LIMIT :limit OFFSET :offset';

$select_sth = $dbh->prepare($sql);

$select_sth->bindValue(':login_user_id', $_SESSION['login_user_id'], PDO::PARAM_INT);
$select_sth->bindValue(':limit', $limit, PDO::PARAM_INT);
$select_sth->bindValue(':offset', $offset, PDO::PARAM_INT);

$select_sth->execute();
$posts = $select_sth->fetchAll(PDO::FETCH_ASSOC);

// ajax=1の場合はJSONを返して終了（HTMLは出力しない）
if (!empty($_GET['ajax'])) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($posts);
  exit;
}

// bodyのHTMLを出力するための関数を用意する
function bodyFilter (?string $body): string
{
  if ($body === null || $body === '') {
    return '';
  }
  
  $body = htmlspecialchars($body); // エスケープ処理
  $body = nl2br($body); // 改行文字を<br>要素に変換

  // >>1といった文字列を該当番号の投稿へのページ内リンクとする（レスアンカー機能）
  // 「>」（半角の大なり記号）はhtmlspecialchars()でエスケープされているため注意
  $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);

  return $body;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>タイムライン</title>
<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
</head>
<body>
  <!-- ヘッダー部分 -->
  <div>
    現在 <?= htmlspecialchars($user['username']) ?> (ID: <?= htmlspecialchars($user['id']) ?>)さんでログイン中
  </div>

  <div style="margin-bottom: 1em;">
    <a href="/setting/index.php">設定画面</a>
    /
    <a href="/users.php">会員一覧画面</a>
  </div>
    
  <!-- 投稿フォーム -->
  <form method="POST" action="./timeline.php">
      <textarea name="body" required></textarea>
      <div style="margin: 1em 0;">
        <label>画像（最大4枚）</label>
        <input type="file" accept="image/*" name="image" id="imageInput" multiple>
      </div>
      <input id="imageBase64Input" type="hidden" name="image_base64">
      <canvas id="imageCanvas" style="display: none;"></canvas>
      <button type="submit">送信</button>
    </form>
    <hr>

    <!-- 投稿を表示するコンテナ（JavaScriptで描画される） -->
    <div class="container" id="posts"></div>
    
    <!-- スクロール監視用の要素 -->
    <div id="sentinel" style="height: 1px;"></div>
  <script>
  // 画像処理（最大4枚）
  document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('imageInput');
    const imageBase64Input = document.getElementById('imageBase64Input');
    const canvas = document.getElementById('imageCanvas');

    imageInput.addEventListener('change', function() {
      if (imageInput.files.length < 1) {
        imageBase64Input.value = '';
        return;
      }

      // 最大4枚まで処理
      const files = Array.from(imageInput.files).slice(0, 4);
      const base64Array = [];
      let processedCount = 0;

      files.forEach((file) => {
        if (!file.type.startsWith('image/')) {
        	processedCount++;
          if (processedCount === files.length) {
            imageBase64Input.value = JSON.stringify(base64Array);
          }
          return;
        }

        const reader = new FileReader();
        const image = new Image();

        reader.onload = () => {
          image.onload = () => {
            const originalWidth = image.naturalWidth;
            const originalHeight = image.naturalHeight;
            const maxLength = 1000;

            if (originalWidth <= maxLength && originalHeight <= maxLength) {
              canvas.width = originalWidth;
              canvas.height = originalHeight;
            } else if (originalWidth > originalHeight) {
              canvas.width = maxLength;
              canvas.height = maxLength * originalHeight / originalWidth;
            } else {
              canvas.width = maxLength * originalWidth / originalHeight;
              canvas.height = maxLength;
            }

            const context = canvas.getContext('2d');
            context.drawImage(image, 0, 0, canvas.width, canvas.height);

            const base64 = canvas.toDataURL();
            base64Array.push(base64);

            processedCount++;

            if (processedCount === files.length) {
              imageBase64Input.value = JSON.stringify(base64Array);
            }
          };
          image.src = reader.result;
        };
        reader.readAsDataURL(file);
      });
    });
  });

  // 無限スクロール
  const limit = 20;
  let offset = 0;
  let loading = false;
  let allLoaded = false;

  async function fetchPosts() {
    if (loading || allLoaded) return;
    loading = true;
    const res = await fetch(`/timeline.php?ajax=1&offset=${offset}&limit=${limit}`);
    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) {
      allLoaded = true;
      loading = false;
      return;
    }
    renderPosts(data);
    offset += data.length;
    loading = false;
  }

  function renderPosts(list) {
    const container = document.getElementById('posts');
    if (!container) return;
      
    list.forEach(p => {
      const postElement = document.createElement('dl');
      postElement.style.marginBottom = '1em';
      postElement.style.paddingBottom = '1em';
      postElement.style.borderBottom = '1px solid #ccc';
        
      // 画像を配列で取得（nullでないもののみ）
      const images = [];
      for (let i = 1; i <= 4; i++) {
        const imageKey = 'image_filename' + i;
        if (p[imageKey]) {
          images.push(p[imageKey]);
        }
      }

      // 画像表示用のHTMLを生成（2列グリッド）
      let imagesHtml = '';
      if (images.length > 0) {
        imagesHtml = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 5px; margin-top: 10px;">';
        images.forEach(img => {
          imagesHtml += `<div><img src="/image/${escapeHtml(img)}" style="width: 100%; height: auto; border-radius: 4px; object-fit: contain; max-height: 200px;"></div>`;
        });
        imagesHtml += '</div>';
      }
        
      postElement.innerHTML = `
        <dt id="entry${p.id}">番号</dt>
        <dd>${escapeHtml(p.id)}</dd>
        <dt>投稿者</dt>
        <dd>
          <a href="/profile.php?user_id=${p.user_id}">
            ${p.user_icon_filename ? `<img src="/upload/image/${escapeHtml(p.user_icon_filename)}" style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">` : ''}
            ${escapeHtml(p.user_name)} (ID: ${escapeHtml(p.user_id)})
          </a>
        </dd>
        <dt>日時</dt>
        <dd>${escapeHtml(p.created_at)}</dd>
        <dt>内容</dt>
        <dd>
          ${escapeHtml(p.content ?? '')}
          ${imagesHtml}
        </dd>
      `;
      container.appendChild(postElement);
    });
  }

  function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m =>
      ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])
    );
  }

  // DOMContentLoadedで実行
  document.addEventListener('DOMContentLoaded', function() {
    const sentinel = document.getElementById('sentinel');
      
    if (!sentinel) {
      console.error('sentinel要素が見つかりません');
      return;
    }
      
    const io = new IntersectionObserver((entries) => {
      if (entries[0].isIntersecting) fetchPosts();
    });
      
    io.observe(sentinel);
      
    // 初回ロード
    fetchPosts();
  });
  </script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
</body>
</html>
