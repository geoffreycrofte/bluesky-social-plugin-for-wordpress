# Architecture Patterns

**Domain:** WordPress Plugin — Multi-Account Bluesky Integration
**Researched:** 2026-02-14
**Confidence:** MEDIUM

## Recommended Architecture

Layered service architecture with dependency injection, decomposing current monolithic classes into focused components.

### Component Boundaries

| Component | Responsibility | Communicates With |
|-----------|---------------|-------------------|
| **Service_Container** | Bootstrap, dependency wiring | All components (initialization only) |
| **Hook_Loader** | Register all WordPress hooks/filters | All controllers |
| **Account_Service** | Account CRUD, switching, active account | Account_Repository, Settings_Manager |
| **Account_Repository** | Data persistence for accounts | WordPress Options API |
| **Post_Service** | Fetch/transform Bluesky posts | API_Handler, Cache_Manager, Account_Service |
| **Discussion_Service** | Fetch/process discussion threads | API_Handler, Cache_Manager |
| **Syndication_Service** | Syndicate WP posts to Bluesky | API_Handler, Account_Service, WP-Cron |
| **Rendering_Service** | Generate HTML from data objects | Post_Service, Discussion_Service |
| **Settings_Manager** | Centralized settings access (multi-account aware) | WordPress Options API |
| **Cache_Manager** | Account-scoped cache operations | WordPress Transients API |
| **API_Handler** | HTTP communication with Bluesky API | Bluesky xRPC endpoints |
| **Admin_Controller** | Admin page routing, form handling | All Services |
| **Block_Controller** | Gutenberg block registration/rendering | Rendering_Service |
| **Widget_Controller** | Widget registration/rendering | Rendering_Service |
| **AJAX_Controller** | AJAX endpoint handling | All Services |

### Data Model

#### Account Entity
```php
class BlueSky_Account {
    public $id;           // Unique identifier (UUID)
    public $name;         // User-defined label ("Personal", "Company")
    public $handle;       // Bluesky handle
    public $app_password; // Encrypted app password
    public $did;          // Decentralized ID
    public $is_active;    // Current active account
    public $owner_id;     // WordPress user ID (0 = shared/admin account)
    public $created_at;   // Timestamp
    public $settings;     // Account-specific overrides
}
```

#### Settings Storage Pattern
```
Option Key: bluesky_accounts → [
    'uuid-1' => [account data...],
    'uuid-2' => [account data...]
]

Option Key: bluesky_global_settings → [
    'cache_duration' => 300,
    'enable_discussions' => true,
    'syndicate_by_default' => false,
    'default_syndication_format' => 'rich_card'
]

Option Key: bluesky_active_account → 'uuid-1'
```

### Data Flow

#### Rendering Flow (Shortcode/Block)
```
1. User inserts [bluesky_posts] shortcode or block
2. Controller → Rendering_Service::render_posts($attrs)
3. Rendering_Service → Post_Service::get_posts($account_id, $params)
4. Post_Service → Cache_Manager::get($cache_key, $account_id)
5. Cache miss? → Post_Service → API_Handler::request('getFeed', ...)
6. Post_Service → Cache_Manager::set(...)
7. Rendering_Service → Generates HTML from Post objects
8. Returns HTML to WordPress
```

#### Multi-Account Syndication Flow
```
1. User publishes post with accounts selected for syndication
2. WordPress fires 'transition_post_status' hook
3. Syndication_Service::on_post_publish($post_id)
4. Gets selected accounts from post meta
5. Schedules async job per account via WP-Cron/Action Scheduler
6. Each job: authenticate → create record → store result in post meta
```

#### Account Switching Flow
```
1. Admin switches active account
2. Account_Service::switch_account($account_id)
3. do_action('bluesky_after_account_switch', $new, $old)
4. Cache_Manager invalidates old account cache
5. Frontend refreshes
```

## Patterns to Follow

### Pattern 1: Dependency Injection via Constructor
**What:** Pass dependencies to constructors, store as properties
**When:** All service classes
**Why:** Testability, flexibility, explicit dependencies

```php
class Post_Service {
    private $api_handler;
    private $cache_manager;
    private $account_service;

    public function __construct($api_handler, $cache_manager, $account_service) {
        $this->api_handler = $api_handler;
        $this->cache_manager = $cache_manager;
        $this->account_service = $account_service;
    }
}
```

### Pattern 2: Repository Pattern for Data Access
**What:** Encapsulate all data persistence logic in repositories
**When:** Accessing WordPress options, post meta, transients
**Why:** Centralized data access, easier to test and migrate

### Pattern 3: Account-Scoped Caching
**What:** Include account ID in all cache keys
**When:** All caching operations
**Why:** Prevent cache pollution between accounts

```php
class Cache_Manager {
    private function scope_key($key, $account_id) {
        return "bluesky_{$account_id}_{$key}";
    }
}
```

### Pattern 4: Template Method for Rendering
**What:** Base renderer with overridable templates
**When:** All HTML generation
**Why:** Theme compatibility, consistent structure, filterable output

### Pattern 5: Event-Driven for Multi-Account Operations
**What:** Fire custom WordPress actions on account operations
**When:** Account switching, syndication, cache invalidation
**Why:** Extensibility, decoupling between components

### Pattern 6: Progressive Disclosure in UI
**What:** Show complexity only when relevant
**When:** Account selector (only when 2+ accounts), advanced options
**Why:** Simple experience for simple setups

## Anti-Patterns to Avoid

### Anti-Pattern 1: God Objects
**What:** Single class handling UI, business logic, data access, caching (current Plugin_Setup)
**Why bad:** Untestable, merge conflicts, impossible to extend
**Instead:** Split by responsibility into services and controllers

### Anti-Pattern 2: Direct WordPress Function Calls in Business Logic
**What:** Calling `get_option()`, `update_post_meta()` directly in services
**Why bad:** Tight coupling, impossible to unit test
**Instead:** Wrap in repositories/managers, inject as dependencies

### Anti-Pattern 3: Mixing Presentation and Business Logic
**What:** HTML generation inside service methods
**Why bad:** Can't reuse logic for different outputs (REST API, CLI, AJAX)
**Instead:** Services return data objects, renderers generate HTML

### Anti-Pattern 4: Passing Account Context Everywhere
**What:** Every method signature becomes `do_thing($param1, $param2, $account_id)`
**Why bad:** Cluttered APIs, easy to forget
**Instead:** Default to active account, use optional parameter with smart default

## Decomposition Strategy (Build Order)

### Phase 1: Foundation (No Breaking Changes)
Extract infrastructure without changing current functionality:
1. Settings_Manager — wrap `get_option()` calls
2. Cache_Manager — wrap transient operations
3. Service_Container — bootstrap/dependency wiring
4. Hook_Loader — extract hook registration

**Why first:** Pure extraction, no logic changes. Every other component needs them.

### Phase 2: Data Layer (Prepares for Multi-Account)
Introduce account abstraction:
1. Account Entity — model for account data
2. Account_Repository — CRUD for accounts
3. Account_Service — business logic for accounts
4. Migration routine — single account → multi-account structure

**Why second:** Data model must exist before services can use it.

### Phase 3: Service Extraction (Decompose Plugin_Setup)
Break monolithic class into focused services:
1. Post_Service — extract post fetching logic
2. Discussion_Service — extract discussion logic
3. Syndication_Service — extract syndication logic
4. Rendering components — extract HTML generation

**Why third:** Services need Settings_Manager, Cache_Manager, Account_Service.

### Phase 4: Controller Layer
Separate controllers from services:
1. Admin_Controller — admin page logic
2. AJAX_Controller — AJAX handlers
3. Block_Controller — block registration/rendering
4. Widget_Controller — widget logic

**Why fourth:** Controllers orchestrate services. Services must exist first.

### Dependency Graph
```
Settings_Manager ─┐
Cache_Manager ────┼─→ Account_Repository → Account_Service ─┐
                  │                                          │
                  └─→ Post_Service ─────────────────────────┼→ Rendering_Service
                  └─→ Discussion_Service ───────────────────┼→ Admin_Controller
                  └─→ Syndication_Service ──────────────────┘

Hook_Loader → (wires everything together)
```

## Proposed File Structure

```
/classes
    /Entities/        Account.php, Post.php, Discussion_Thread.php
    /Repositories/    Account_Repository.php
    /Services/        Account_Service.php, Post_Service.php, Discussion_Service.php,
                      Rendering_Service.php, Syndication_Service.php,
                      Settings_Manager.php, Cache_Manager.php
    /Controllers/     Admin_Controller.php, AJAX_Controller.php,
                      Block_Controller.php, Widget_Controller.php
    /Infrastructure/  API_Handler.php, Hook_Loader.php, Service_Container.php
    /Renderers/       Post_Renderer.php, Discussion_Renderer.php, Profile_Renderer.php
    /Legacy/          (during migration — gradually emptied)
/templates/           post-card.php, discussion-thread.php, profile-card.php
```

## Scalability Considerations

| Concern | 1 Account | 5 Accounts | 20+ Accounts |
|---------|-----------|------------|--------------|
| Account Storage | Single option array | Indexed option array | Consider custom table |
| Cache Strategy | Account-scoped transients | + LRU eviction | Object cache (Redis) |
| API Rate Limits | Per-account tracking | Queue system | Circuit breaker pattern |
| Syndication | Async per publish | Action Scheduler | Priority queue with retry |

## Sources

- WordPress core architectural patterns (WP_REST_Controller, WP_Widget)
- PHP OOP design patterns (Repository, Service Layer, DI)
- Current codebase analysis
- WooCommerce architecture (enterprise WordPress plugin patterns)
- Action Scheduler library by Automattic

---
*Architecture research: 2026-02-14*
