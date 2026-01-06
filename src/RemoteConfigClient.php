<?php

namespace DavidCanial\RemoteConfigTool;

use Dotenv\Dotenv;
use GuzzleHttp\Client;

class RemoteConfigClient
{
    private Client $http;
    private string $endpoint;

    /**
     * コンストラクタ
     *
     * @param string|null $projectId Firebase プロジェクト ID（省略時は環境変数 FIREBASE_PROJECT_ID を利用）
     * @param Client|null $http      カスタム Guzzle クライアント（省略時はデフォルトを生成）
     */
    public function __construct(?string $projectId = null, ?Client $http = null)
    {
        $this->http = $http ?? new Client(["timeout" => 30]);

        // projectId を引数または環境変数から解決
        $projectId = $projectId
            ?? ($_ENV["FIREBASE_PROJECT_ID"] ?? getenv("FIREBASE_PROJECT_ID"));

        if (empty($projectId)) {
            throw new \RuntimeException(
                "FIREBASE_PROJECT_ID が設定されていません。".
                    "RemoteConfigClient のコンストラクタに projectId を渡すか、".
                    "環境変数 FIREBASE_PROJECT_ID を設定してください。",
            );
        }

        $this->endpoint = sprintf(
            "https://firebaseremoteconfig.googleapis.com/v1/projects/%s/remoteConfig",
            $projectId,
        );
    }

    /**
     * .env ファイルを読み込み、RemoteConfigClient インスタンスを生成するヘルパーです。
     *
     * 使用例:
     *   $client = RemoteConfigClient::createFromEnvFile(__DIR__);
     *
     * @param string      $directory .env ファイルが置かれているディレクトリ
     * @param string      $file      .env ファイル名（デフォルト ".env"）
     * @param string|null $projectId Firebase プロジェクト ID（指定時は環境変数より優先）
     */
    public static function createFromEnvFile(
        string $directory,
        string $file = ".env",
        ?string $projectId = null
    ): self {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException(
                "指定されたディレクトリが存在しません: {$directory}",
            );
        }

        $path = rtrim($directory, DIRECTORY_SEPARATOR) .
            DIRECTORY_SEPARATOR .
            $file;

        if (!file_exists($path)) {
            throw new \RuntimeException(".env ファイルが見つかりません: {$path}");
        }

        Dotenv::createImmutable($directory, $file)->load();

        return new self($projectId);
    }

    /**
     * Firebase 用アクセストークンを取得します。
     */
    public function getToken(): string
    {
        return FirebaseAuth::getAccessToken();
    }

    /**
     * Remote Config の内容を ETag 付きで取得します。
     *
     * @return array{etag:string,data:array}
     */
    public function get(): array
    {
        $token = FirebaseAuth::getAccessToken();

        $response = $this->http->get($this->endpoint, [
            "headers" => [
                "Authorization" => "Bearer " . $token,
                "Accept-Encoding" => "gzip",
            ],
        ]);

        // ETag は大文字/小文字の異なるヘッダー名で返ってくる可能性があるため両方を確認
        $etag = $response->getHeaderLine("ETag");
        if (empty($etag)) {
            $etag = $response->getHeaderLine("etag");
        }

        return [
            "etag" => $etag,
            "data" => json_decode($response->getBody()->getContents(), true),
        ];
    }

    /**
     * ETag のみを取得します（本体データは返しません）。
     */
    public function getEtag(): string
    {
        $result = $this->get();
        return $result["etag"];
    }

    /**
     * Remote Config の本体データのみを取得します（ETag は含まれません）。
     */
    public function getConfig(): array
    {
        $result = $this->get();
        return $result["data"];
    }

    /**
     * Remote Config を更新します。
     *
     * @param array  $config 更新する設定内容（通常は "parameters" キーを含む配列）
     * @param string $etag   直前の GET で取得した ETag
     *
     * @return array{success:bool,etag:string,data:array} 新しい ETag とレスポンスボディ
     */
    public function update(array $config, string $etag): array
    {
        if (empty($etag)) {
            throw new \InvalidArgumentException(
                "更新には ETag が必須です。先に get() メソッドで最新の ETag を取得してください。",
            );
        }

        $token = FirebaseAuth::getAccessToken();

        $response = $this->http->put($this->endpoint, [
            "headers" => [
                "Authorization" => "Bearer " . $token,
                "If-Match" => $etag,
                "Content-Type" => "application/json; charset=utf-8",
            ],
            "json" => $config,
        ]);

        $newEtag = $response->getHeaderLine("ETag");
        if (empty($newEtag)) {
            $newEtag = $response->getHeaderLine("etag");
        }

        return [
            "success" => true,
            "etag" => $newEtag,
            "data" => json_decode($response->getBody()->getContents(), true),
        ];
    }

    /**
     * ETag チェックを無視して Remote Config を強制更新します。
     *
     * 複数のクライアントから同時に更新している場合、他の変更を上書きする可能性があります。
     */
    public function forceUpdate(array $config): array
    {
        $token = FirebaseAuth::getAccessToken();

        $response = $this->http->put($this->endpoint, [
            "headers" => [
                "Authorization" => "Bearer " . $token,
                "If-Match" => "*",
                "Content-Type" => "application/json; charset=utf-8",
            ],
            "json" => $config,
        ]);

        $newEtag = $response->getHeaderLine("ETag");
        if (empty($newEtag)) {
            $newEtag = $response->getHeaderLine("etag");
        }

        return [
            "success" => true,
            "etag" => $newEtag,
            "data" => json_decode($response->getBody()->getContents(), true),
        ];
    }
}
