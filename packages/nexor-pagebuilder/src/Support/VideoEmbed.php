<?php

namespace Nexor\PageBuilder\Support;

/**
 * Ссылка на видео → адрес встраиваемого плеера.
 *
 * На сайт попадает только адрес, собранный здесь из распознанного ID, а не
 * ссылка из формы: в iframe не уйдёт произвольный сайт.
 */
class VideoEmbed
{
    /**
     * @param  array{autoplay?: bool, loop?: bool, muted?: bool, controls?: bool}  $settings
     * @return array{provider: string, id: string, src: string}|null
     */
    public static function parse(?string $url, array $settings = []): ?array
    {
        $url = trim((string) $url);

        if ($url === '' || ! preg_match('~^https?://~i', $url)) {
            return null;
        }

        $patterns = [
            'youtube' => [
                '~^https?://(?:www\.|m\.)?youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)([A-Za-z0-9_-]{11})~i',
                '~^https?://youtu\.be/([A-Za-z0-9_-]{11})~i',
            ],
            'vimeo' => ['~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d{6,12})~i'],
            'rutube' => ['~^https?://(?:www\.)?rutube\.ru/(?:video|play/embed)/([a-f0-9]{32})~i'],
            'vk' => ['~^https?://(?:www\.)?(?:vk\.com|vkvideo\.ru)/video(-?\d+_\d+)~i'],
        ];

        foreach ($patterns as $provider => $list) {
            foreach ($list as $pattern) {
                if (preg_match($pattern, $url, $match)) {
                    return ['provider' => $provider, 'id' => $match[1], 'src' => self::src($provider, $match[1], $settings)];
                }
            }
        }

        return null;
    }

    /**
     * @param  array{autoplay?: bool, loop?: bool, muted?: bool, controls?: bool}  $settings
     */
    protected static function src(string $provider, string $id, array $settings): string
    {
        $params = [];

        if (in_array($provider, ['youtube', 'vimeo'], true)) {
            if ($settings['autoplay'] ?? false) {
                $params['autoplay'] = 1;
            }
            if ($settings['muted'] ?? false) {
                $params[$provider === 'youtube' ? 'mute' : 'muted'] = 1;
            }
            if ($settings['loop'] ?? false) {
                $params['loop'] = 1;

                // YouTube зацикливает только плейлист — из одного этого видео.
                if ($provider === 'youtube') {
                    $params['playlist'] = $id;
                }
            }
            if (($settings['controls'] ?? true) === false) {
                $params['controls'] = 0;
            }
        }

        $query = $params === [] ? '' : '?'.http_build_query($params);

        return match ($provider) {
            'youtube' => 'https://www.youtube.com/embed/'.$id.$query,
            'vimeo' => 'https://player.vimeo.com/video/'.$id.$query,
            'rutube' => 'https://rutube.ru/play/embed/'.$id,
            'vk' => 'https://vkvideo.ru/video_ext.php?oid='.strstr($id, '_', true).'&id='.substr((string) strstr($id, '_'), 1),
        };
    }
}
