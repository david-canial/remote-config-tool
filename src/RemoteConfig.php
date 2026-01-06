<?php

namespace DavidCanial\RemoteConfigTool;

use GuzzleHttp\Client;

/**
 * Firebase Remote Config 用のシンプルな静的ファサードクラスです。
 *
 * Composer でパッケージをインストールしたあとの想定利用方法:
 *
 *   use Fakevendor\RemoteConfigTool\RemoteConfig;
 *
 *   $etag  = RemoteConfig::etag();   // 現在の ETag を取得
 *   $data  = RemoteConfig::config(); // Remote Config 本体を取得
 *   $token = RemoteConfig::token();  // アクセストークンを取得
 *
 * 既定では環境変数 FIREBASE_PROJECT_ID を参照しますが、
 * 明示的に $projectId を渡すこともできます。
 */
class RemoteConfig
{
    /**
     * 内部で利用する RemoteConfigClient インスタンスを生成します。
     *
     * @param string|null $projectId Firebase プロジェクト ID
     * @param Client|null $http      Guzzle クライアント
     */
    protected static function client(
        ?string $projectId = null,
        ?Client $http = null
    ): RemoteConfigClient {
        return new RemoteConfigClient($projectId, $http);
    }

    /**
     * Firebase のアクセストークンを取得します。
     */
    public static function token(?string $projectId = null): string
    {
        return static::client($projectId)->getToken();
    }

    /**
     * Remote Config の内容（ETag 付き）を取得します。
     *
     * @return array{etag: string, data: array}
     */
    public static function get(?string $projectId = null): array
    {
        return static::client($projectId)->get();
    }

    /**
     * 現在の ETag のみを取得します。
     */
    public static function etag(?string $projectId = null): string
    {
        return static::client($projectId)->getEtag();
    }

    /**
     * Remote Config の本体データのみを取得します（ETag なし）。
     */
    public static function config(?string $projectId = null): array
    {
        return static::client($projectId)->getConfig();
    }

    /**
     * ETag を指定して Remote Config を更新します。
     *
     * @param array       $config    更新内容
     * @param string      $etag      直前の GET で取得した ETag
     * @param string|null $projectId Firebase プロジェクト ID
     */
    public static function update(
        array $config,
        string $etag,
        ?string $projectId = null
    ): array {
        return static::client($projectId)->update($config, $etag);
    }

    /**
     * ETag を無視して Remote Config を強制更新します。
     *
     * @param array       $config    更新内容
     * @param string|null $projectId Firebase プロジェクト ID
     */
    public static function forceUpdate(
        array $config,
        ?string $projectId = null
    ): array {
        return static::client($projectId)->forceUpdate($config);
    }
}


