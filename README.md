# インストール手順

## 1. DockerおよびDockerComposeのインストール

### サーバー上で以下のコマンドを実行し、Dockerをインストール

    sudo yum install -y docker
    sudo systemctl start docker
    sudo systemctl enable docker
 
### デフォルトのユーザー(ec2-user)でもsudoをつけずにdockerコマンドを実行できるように、dockerグループに追加

    sudo usermod -aG docker ec2-user

usermodを反映するために一度ログアウトする必要があります。  
sshの場合は一度ログアウトしログインしなおすことで反映させることができます。
  　
### Docker Composeのインストール

    sudo mkdir -p /usr/local/lib/docker/cli-plugins/
    sudo curl -SL https://github.com/docker/compose/releases/download/v2.36.0/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose
    sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

インストールできたかの確認

    docker compose version

## 2. Git インストール

    sudo yum install git -y

初期設定

    git config --global init.defaultBranch main

名前とメールアドレスを設定する。メールアドレスはGitHubに登録しているものと同一のものにする。

    git config --global user.name "お名前 ほげ太郎"
    git config --global user.email "kokoni-mail-address-iretene@example.com"

## 3. ソースコードの配置

    git clone https://github.com/Hirooki11/hiroki-Wed34.git

## 4. ビルド＆起動

    cd hiroki-Wed34

### screenのインストール

多くのLinuxディストリビューションでは標準で入っていますが，インストール方法は以下の通りです。  

yumの場合(amazon linux2, centos, redhat などの場合)

    sudo yum install screen -y

aptの場合(debian ubuntu などの場合)

    sudo apt install screen -y

### screenを起動する

    screen

### docker composeをビルド・起動する

    docker compose build
    docker compose up

## 5. テーブルの作成

作成したDockerコンテナ内のMySQLサーバーにmysqlコマンドで接続する

    docker compose exec mysql mysql example_db

掲示板の投稿を保存するテーブルを作成する  

    CREATE TABLE `bbs_entries` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `body` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `image_filename` TEXT DEFAULT NULL
    ); 

上記のコマンドで起動できたら、ウェブブラウザでEC2インスタンスのホスト名またはIPアドレス(SSHでログインするときと同じもの)に接続する。  

ブラウザのURLに`http://IPアドレス/timeline.php`と入力して開いてみる
ログイン画面にリダイレクトし、ログイン後タイムラインが表示されたら成功
