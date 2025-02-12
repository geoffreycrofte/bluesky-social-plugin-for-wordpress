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
    public function __construct( $options ) {
        $this -> options = $options;
    }

    /**
     * Authenticate the user by creating and storing an access and refresh jwt.
     *
     * @param mixed $force Wether or not to force the refresh token creation.
     * @return bool
     */
    public function authenticate( $force = false ) {
        // Check if credentials are set
        if ( ! isset( $this->options['handle'] ) || ! isset( $this->options['app_password'] ) ) {
            return false;
        }

        $helpers = new BlueSky_Helpers();
        $access_tkey = $helpers -> get_access_token_transient_key();
        $refresh_tkey = $helpers -> get_refresh_token_transient_key();
        $did_tkey = $helpers->get_did_transient_key();
    
        // Retrieve saved tokens from transients
        $access_token = get_transient( $access_tkey );
        $refresh_token = get_transient( $refresh_tkey );
        $did = get_transient( $did_tkey );
    
        // If an access token exists and hasn't expired, use it
        if ( $access_token && ! $force ) {
            $this->access_token = $access_token;
            $this->did = $did;
            return true;
        }
    
        // Check if refresh token exists to renew the access token
        if ( $refresh_token && ! $force ) {
            $response = wp_remote_post( $this->bluesky_api_url . 'com.atproto.server.refreshSession', [
                'body'    => wp_json_encode([
                    'refreshJwt' => $refresh_token
                ]),
                'headers' => [
                    'Authorization' => 'Bearer ' . $refresh_token,
                    'Content-Type' => 'application/json',
                ]
            ]);
    
            if ( is_wp_error( $response ) ) {
                return false;
            }
    
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
            if ( isset( $body['accessJwt'] ) && isset( $body['refreshJwt'] ) && isset( $body['did'] ) ) {
                // Save new tokens
                set_transient( $access_tkey, $body['accessJwt'], HOUR_IN_SECONDS );
                set_transient( $refresh_tkey, $body['refreshJwt'], WEEK_IN_SECONDS );
                set_transient( $did_tkey, $body['did'] );
    
                $this -> access_token = $body['accessJwt'];
                $this -> did = $body['did'];
                return true;
            }
            
            // Force re-authentication if refresh token is invalid
            delete_transient( $refresh_tkey );
            delete_transient( $access_tkey );
            delete_transient( $did_tkey );

            return $this -> authenticate( $force );
        }
    
        // No valid tokens, 
        // or forced authentication,
        // then proceed with full authentication
        $password = $this -> options['app_password'];
        $password = $helpers -> bluesky_decrypt( $password );
    
        $response = wp_remote_post( $this->bluesky_api_url . 'com.atproto.server.createSession', [
            'body'    => wp_json_encode([
                'identifier' => $this->options['handle'],
                'password'   => $password
            ]),
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
    
        if ( is_wp_error( $response ) ) {
            return false;
        }
    
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
        if ( isset( $body['did'] ) && isset( $body['accessJwt'] ) && isset( $body['refreshJwt'] ) ) {
            // Save tokens in transients
            set_transient( $access_tkey, $body['accessJwt'], HOUR_IN_SECONDS );
            set_transient( $refresh_tkey, $body['refreshJwt'], WEEK_IN_SECONDS );
            set_transient( $did_tkey, $body['did'] );
    
            $this -> did = $body['did'];
            $this -> access_token = $body['accessJwt'];
            return true;
        }
    
        return false;
    }

    /**
     * Destroy the refresh token and its recordings in database to logout the user.
     *
     * @return bool
     */
    public function logout() {
        $helpers = new BlueSky_Helpers();
        try {
            // clean the transient of the jwt
            $this -> cleanup_session_data($helpers);
            // clean the handle and app_password options
            $this -> cleanup_login_options();
            // done.
            return true;
            
        } catch (Exception $e) {
            error_log('Bluesky Exception during logout: ' . $e -> getMessage());
            return false;
        }
    }
    
    /**
     * Clean the session information
     *
     * @param mixed $helpers
     * @param mixed $access_tkey
     * @return void
     */
    private function cleanup_session_data($helpers) {
        delete_transient( $helpers -> get_access_token_transient_key() );
        delete_transient( $helpers -> get_refresh_token_transient_key() );
        delete_transient( $helpers -> get_did_transient_key() );
        
        $this -> access_token = null;
        $this -> did = null;
    }

    /**
     * Clean the login and password options
     *
     * @return void
     */
    private function cleanup_login_options() {
        $options = $this -> options;
        unset( $options['handle'] );
        unset( $options['app_password'] );

        update_option( BLUESKY_PLUGIN_OPTIONS, $options );
        $this -> options = $options; // shoudn't be necessary, but just in case.
    }

    /**
     * Fetch posts from BlueSky feed
     * @param int $limit Number of posts to fetch (default 10)
     * @return array|false Processed posts or false on failure
     */
    public function fetch_bluesky_posts( $limit = 10, $no_replies = true, $no_reposts = true ) {
        $helpers = new BlueSky_Helpers();
        $no_replies = $no_replies ?? $this -> options['no_replies']  ?? true;
        $no_reposts = $no_reposts ?? $this -> options['no_reposts'] ?? true;
        $cache_key = $helpers -> get_posts_transient_key( $limit, $no_replies, $no_reposts );
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
                'limit' => ( $no_replies || $no_reposts ) ? 100 : $limit, // Fetch more to account for replies
            ]
        ]);

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $raw_posts = json_decode( wp_remote_retrieve_body( $response ), true );

        $filteredFeed = array_filter($raw_posts['feed'], function ($entry) use($no_replies, $no_reposts, $helpers) {
            if ( ! isset( $entry['post'] ) ) {
                return false;
            }
        
            $post = $entry['post'];
        
            // Filter out replies (check if 'reply' key exists in record)
            if ( isset( $post['record']['reply'] ) && $no_replies ) {
                return false;
            }
        
            // Filter out reposts (check if 'reason' key exists and is a repost)
            if ( isset( $entry['reason'] ) && $entry['reason']['$type'] === "app.bsky.feed.defs#reasonRepost"  && $no_reposts) {
                return false;
            }
        
            return true; // Keep original posts
        });
        
        // Reset array keys
        $posts = array_values($filteredFeed);



        if ( $no_replies ) {
            // Limit to the requested number of posts
            $posts = array_slice( $posts, 0, $limit );
        }

        // Process and normalize posts
        $processed_posts = $this -> process_posts( $posts ?? [] );

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
    
        // Construct the post text and calculate the link position
        $text = wp_trim_words( $title, 50 ) . "\n\n" . $permalink;
        $link_start = strpos( $text, $permalink );
        $link_end = $link_start + strlen( $permalink );
    
        // Post data with facets for link embedding
        $post_data = [
            '$type' => 'app.bsky.feed.post',
            'text' => $text,
            'facets' => [
                [
                    'index' => [
                        'byteStart' => $link_start,
                        'byteEnd' => $link_end
                    ],
                    'features' => [
                        [
                            '$type' => 'app.bsky.richtext.facet#link',
                            'uri' => $permalink
                        ]
                    ]
                ]
            ],
            'createdAt' => gmdate( 'c' )
        ];
    
        $response = wp_remote_post( $this->bluesky_api_url . 'com.atproto.repo.createRecord', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode([
                'repo' => $this->did,
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
            $post = $post['post'];
            
            // Extract embedded images
            $images = [];
            if ( isset( $post['embed']['images'] ) ) {
                foreach ( $post['embed']['images'] as $image ) {
                    $images[] = array(
                        'url' => $image['fullsize'] ?? $image['thumb'] ?? '',
                        'alt' => $image['alt'] ?? '',
                        'width' => $image['aspectRatio']['width'] ?? 0,
                        'height' => $image['aspectRatio']['height'] ?? 0,
                    );
                }
            }

            // Extract external media
            $external_media = null;
            if ( isset( $post['embed']['external'] ) ) {
                $external_media = [
                    'uri' => $post['embed']['external']['uri'],
                    'title' => $post['embed']['external']['title'] ?? '',
                    'alt' => $post['embed']['external']['alt'] ?? '',
                    'thumb' => $post['embed']['external']['thumb'] ?? '',
                    'description' => $post['embed']['external']['description'] ?? ''
                ];
            } elseif ( isset( $post['embed']['media'] ) ) {
                $external_media = [
                    'uri' => $post['embed']['media']['external']['uri'],
                    'title' => $post['embed']['media']['external']['title'] ?? '',
                    'alt' => $post['embed']['media']['external']['alt'] ?? '',
                    'thumb' => $post['embed']['media']['external']['thumb'] ?? '',
                    'description' => $post['embed']['media']['external']['description'] ?? ''
                ];
            }

            // Check for video embed
            $embedded_media = $this -> extract_embedded_media( $post );

            $end0fPostURI = isset( $post['uri'] ) ? explode( '/', $post['uri'] ) : array();
            return [
                'text' => $post['record']['text'] ?? 'No text',
                'langs' => $post['record']['langs'] ?? array('en'),
                'url' => 'https://bsky.app/profile/' . ( $post['author']['handle'] ?? '') . '/post/' . (isset( $post['uri']) ? end( $end0fPostURI) : ''),
                'created_at' => $post['record']['createdAt'] ?? '',
                'account' => [
                    'did' => $post['author']['did'] ?? '',
                    'handle' => $post['author']['handle'] ?? '',
                    'display_name' => $post['author']['displayName'] ?? '',
                    'avatar' => $post['author']['avatar'] ?? '',
                ],
                'images' => $images,
                'external_media' => $external_media,
                'embedded_media' => $embedded_media,
                'counts' => [
                    'reply' => $post['replyCount'] ?? '',
                    'repost' => $post['repostCount'] ?? '',
                    'like' => $post['likeCount'] ?? '',
                    'quote' => $post['quoteCount'] ?? ''
                ],
                'facets' => $post['record']['facets'] ?? [],
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
        if ( isset( $post['embed']['video'] ) || 
            ( isset( $post['embed']['$type'] ) && strstr( $post['embed']['$type'], 'app.bsky.embed.video' ) ) ) {

            $video_embed = $post['embed'];
            $embedded_media = [
                'type' => 'video',
                'alt' => $video_embed['alt'] ?? '',
                'width' => $video_embed['aspectRatio']['width'] ?? null,
                'height' => $video_embed['aspectRatio']['height'] ?? null,
                'playlist_url' => $video_embed['playlist'] ?? '',
                'thumbnail_url' => $video_embed['thumbnail'] ?? ''
            ];
        }

        // Record (embedded post) embed
        elseif ( isset( $post['embed']['record'] ) || 
                ( isset( $post['embed']['$type'] ) && strstr( $post['embed']['$type'], 'app.bsky.embed.record') ) ) {
            
            // For some reasons, sometimes the API returns record in the record array. (multi embedded items?)
            $record_embed = isset( $post['embed']['record']['record'] ) ? $post['embed']['record']['record'] : $post['embed']['record'];

            if( 'app.bsky.graph.starterpack' == $record_embed['$type'] ) {
                $end0fURI = explode( '/', $post['embed']['record']['uri'] );
                $author = $post['embed']['record']['creator'];
                $embedded_media = [
                    'type' => 'starterpack',
                    'author' => [
                        'did' => $author['did'] ?? '',
                        'handle' => $author['handle'] ?? '',
                        'display_name' => $author['displayName'] ?? ''
                    ],
                    'title' => $record_embed['name'] ?? '',
                    'text' => $record_embed['description'] ?? '',
                    'created_at' => $record_embed['createdAt'] ?? '',
                    'like_count' => $record_embed['likeCount'] ?? 0,
                    'reply_count' => $record_embed['replyCount'] ?? 0,
                    'url' => 'https://bsky.app/starter-pack/' . ( $author['handle'] ?? '') . '/' . end( $end0fURI )
                ];
            } else {
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
                    'url' => 'https://bsky.app/profile/' . ( $record_embed['author']['handle'] ?? '') . '/post/' . ( $record_embed['uri'] ? end( $end0fURI ) : '')
                ];
            }

            // Check if the embedded record has its own media (like a video)
            if ( isset( $record_embed['value']['embed']['video'] ) ) {

                $embedvideo = $record_embed['value']['embed'];
                $embedded_media['embedded_video'] = [
                    'type' => 'video',
                    'alt' => $embedvideo['alt'] ?? '',
                    'width' => $embedvideo['aspectRatio']['width'] ?? null,
                    'height' => $embedvideo['aspectRatio']['height'] ?? null,
                    'playlist_url' => $embedvideo['playlist'] ?? '',
                    'thumbnail_url' => $embedvideo['thumbnail'] ?? ''
                ];
            }
        }

        return $embedded_media;
    }
}