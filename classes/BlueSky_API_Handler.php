<?php
// Prevent direct access to the plugin
if ( ! defined('ABSPATH') ) {
    exit;
}

class BlueSky_API_Handler {
    /**
     * Base URL for BlueSky API
     * @var string
     */
    private $bluesky_api_url = 'https://bsky.social/xrpc/';

    /**
     * Plugin options
     * @var array
     */
    private $options;

    /**
     * Authenticated user's DID (Decentralized Identifier)
     * @var string|null
     */
    private $did = null;

    /**
     * Access token for authenticated requests
     * @var string|null
     */
    private $access_token = null;

    /**
     * Constructor
     * @param array $options Plugin settings
     */
    public function __construct( $options) {
        $this -> options = $options;
    }

    /**
     * Authenticate with BlueSky API
     * @return bool Whether authentication was successful
     */
    public function authenticate() {
        if ( ! isset( $this -> options['handle'] ) || ! isset( $this -> options['app_password'] ) ) {
            return false;
        }

        $password = $this -> options['app_password'];
        $helpers = new BlueSky_Helpers();
        $password = $helpers -> bluesky_decrypt( $password );

        $response = wp_remote_post( $this -> bluesky_api_url . 'com.atproto.server.createSession', [
            'body' => wp_json_encode([
                'identifier' => $this -> options['handle'],
                'password' => $password
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true);
        
        if ( isset( $body['did'] ) && isset( $body['accessJwt']) ) {
            $this -> did = $body['did'];
            $this -> access_token = $body['accessJwt'];
            return true;
        }

        return false;
    }

    /**
     * Fetch posts from BlueSky feed
     * @param int $limit Number of posts to fetch (default 10)
     * @return array|false Processed posts or false on failure
     */
    public function fetch_bluesky_posts( $limit = 10 ) {
        $helpers = new BlueSky_Helpers();
        $cache_key = $helpers -> get_posts_transient_key( $limit );
        $cache_duration = $this -> options['cache_duration']['total_seconds'] ?? 3600; // Default 1 hour

        // Skip cache if duration is 0
        if ( $cache_duration > 0 ) {
            $cached_posts = get_transient( $cache_key );
            if ( $cached_posts !== false ) {
                return $cached_posts;
            }
        }

        // Ensure authentication
        if ( ! $this -> authenticate() ) {
            return false;
        }

        // Sanitize limit
        $limit = max( 1, min( 10, intval( $limit ) ) );

        $response = wp_remote_get( $this -> bluesky_api_url . 'app.bsky.feed.getAuthorFeed', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this -> access_token
            ],
            'body' => [
                'actor' => $this -> did,
                'limit' => $limit
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $raw_posts = json_decode( wp_remote_retrieve_body( $response ), true );

        // Process and normalize posts
        $processed_posts = $this -> process_posts( $raw_posts['feed'] ?? [] );

        // Sort by most recent first
        usort( $processed_posts, function( $a, $b) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        });

        // Cache the posts if caching is enabled
        if ( $cache_duration > 0 ) {
            set_transient( $cache_key, $processed_posts, $cache_duration );
        }

        return $processed_posts;
    }

    /**
     * Fetch BlueSky profile
     * @return array|false Profile data or false on failure
     */
    public function get_bluesky_profile() {
        $helpers = new BlueSky_Helpers();
        $cache_key = $helpers -> get_profile_transient_key();
        $cache_duration = $this -> options['cache_duration']['total_seconds'] ?? 3600; // Default 1 hour

        // Skip cache if duration is 0
        if ( $cache_duration > 0 ) {
            $cached_profile = get_transient( $cache_key );
            if ( $cached_profile !== false ) {
                return $cached_profile;
            }
        }

        if ( ! $this -> authenticate() ) {
            return false;
        }

        $response = wp_remote_get( $this -> bluesky_api_url . 'app.bsky.actor.getProfile', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this -> access_token
            ],
            'body' => [
                'actor' => $this -> did
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $decoded = json_decode( wp_remote_retrieve_body( $response ), true );

        // Cache the profile if caching is enabled
        if ( $cache_duration > 0 ) {
            set_transient( $cache_key, $decoded, $cache_duration);
        }

        return $decoded;
    }

    /**
     * Syndicate a post to BlueSky
     * @param string $title Post title
     * @param string $permalink Post URL
     * @return bool Whether syndication was successful
     */
    public function syndicate_post_to_bluesky( $title, $permalink ) {
        if ( ! $this -> authenticate() ) {
            return false;
        }

        $post_data = [
            '$type' => 'app.bsky.feed.post',
            'text' => wp_trim_words( $title, 50 ) . "\n\nRead more: " . $permalink,
            'createdAt' => gmdate('c')
        ];

        $response = wp_remote_post( $this -> bluesky_api_url . 'com.atproto.repo.createRecord', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this -> access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'repo' => $this -> did,
                'collection' => 'app.bsky.feed.post',
                'record' => $post_data
            ])
        ]);

        return !is_wp_error( $response );
    }

    /**
     * Process raw BlueSky posts into a normalized format
     * @param array $raw_posts Raw posts from BlueSky API
     * @return array Processed posts
     */
    private function process_posts( $raw_posts ) {
        return array_map(function( $post ) {
            // Extract embedded images
            $images = [];
            if ( isset( $post['post']['embed']['images'] ) ) {
                foreach ( $post['post']['embed']['images'] as $image ) {
                    $images[] = $image['fullsize'] ?? $image['thumb'] ?? '';
                }
            }

            // Extract external media
            $external_media = null;
            if ( isset( $post['post']['embed']['external'] ) ) {
                $external_media = [
                    'uri' => $post['post']['embed']['external']['uri'],
                    'title' => $post['post']['embed']['external']['title'] ?? '',
                    'alt' => $post['post']['embed']['external']['alt'] ?? '',
                    'description' => $post['post']['embed']['external']['description'] ?? ''
                ];
            }

            // Check for video embed
            $embedded_media = $this -> extract_embedded_media( $post );

            $end0fPostURI = isset( $post['post']['uri'] ) ? explode( '/', $post['post']['uri'] ) : array();
            return [
                'text' => $post['post']['record']['text'] ?? 'No text',
                'created_at' => $post['post']['record']['createdAt'] ?? '',
                'account' => [
                    'did' => $post['post']['author']['did'] ?? '',
                    'handle' => $post['post']['author']['handle'] ?? '',
                    'display_name' => $post['post']['author']['displayName'] ?? '',
                    'avatar' => $post['post']['author']['avatar'] ?? '',
                ],
                'images' => $images,
                'external_media' => $external_media,
                'embedded_media' => $embedded_media,
                'url' => 'https://bsky.app/profile/' . ( $post['post']['author']['handle'] ?? '') . '/post/' . (isset( $post['post']['uri']) ? end( $end0fPostURI) : '')
            ];
        }, $raw_posts);
    }

    /**
     * Extract embedded media from a post
     * @param array $post Post data
     * @return array|null Embedded media details
     */
    private function extract_embedded_media( $post) {
        $embedded_media = null;

        // Video embed
        if (isset( $post['post']['embed']['video']) || 
            (isset( $post['post']['embed']['$type']) && $post['post']['embed']['$type'] === 'app.bsky.embed.video')) {
            $video_embed = $post['post']['embed'];
            $embedded_media = [
                'type' => 'video',
                'alt' => $video_embed['alt'] ?? '',
                'aspect_ratio' => [
                    'width' => $video_embed['aspectRatio']['width'] ?? null,
                    'height' => $video_embed['aspectRatio']['height'] ?? null
                ],
                'video_details' => [
                    'mime_type' => $video_embed['video']['mimeType'] ?? '',
                    'size' => $video_embed['video']['size'] ?? 0,
                    'playlist_url' => $video_embed['embeds'][0]['playlist'] ?? '',
                    'thumbnail_url' => $video_embed['embeds'][0]['thumbnail'] ?? ''
                ]
            ];
        } 
        // Record (embedded post) embed
        elseif ( isset( $post['post']['embed']['record'] ) || 
                ( isset( $post['post']['embed']['$type'] ) && $post['post']['embed']['$type'] === 'app.bsky.embed.record') ) {
            $record_embed = $post['post']['embed']['record'];
            $end0fURI = explode( '/', $record_embed['uri'] );
            $embedded_media = [
                'type' => 'record',
                'author' => [
                    'did' => $record_embed['author']['did'] ?? '',
                    'handle' => $record_embed['author']['handle'] ?? '',
                    'display_name' => $record_embed['author']['displayName'] ?? ''
                ],
                'text' => $record_embed['value']['text'] ?? '',
                'created_at' => $record_embed['value']['createdAt'] ?? '',
                'like_count' => $record_embed['likeCount'] ?? 0,
                'reply_count' => $record_embed['replyCount'] ?? 0,
                'url' => 'https://bsky.app/profile/' . ( $record_embed['author']['handle'] ?? '') . '/post/' . ( $record_embed['uri'] ? end( $end0fURI) : '')
            ];

            // Check if the embedded record has its own media (like a video)
            if ( isset( $record_embed['value']['embed']['video'] ) ) {
                $embedded_media['embedded_video'] = [
                    'alt' => $record_embed['value']['embed']['alt'] ?? '',
                    'aspect_ratio' => [
                        'width' => $record_embed['value']['embed']['aspectRatio']['width'] ?? null,
                        'height' => $record_embed['value']['embed']['aspectRatio']['height'] ?? null
                    ],
                    'video_details' => [
                        'mime_type' => $record_embed['value']['embed']['video']['mimeType'] ?? '',
                        'size' => $record_embed['value']['embed']['video']['size'] ?? 0
                    ]
                ];
            }
        }

        return $embedded_media;
    }
}