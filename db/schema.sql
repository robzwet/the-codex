-- The Codex — database schema.
-- All statements are idempotent (IF NOT EXISTS) so migrate.php can run them on every boot.

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_username (username),
    UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    description TEXT         NULL,
    invite_code CHAR(10)     NOT NULL,
    created_by  INT UNSIGNED NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_invite_code (invite_code),
    CONSTRAINT fk_campaign_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_members (
    campaign_id INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    role        ENUM('player','gm') NOT NULL DEFAULT 'player',
    joined_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (campaign_id, user_id),
    CONSTRAINT fk_member_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_member_user     FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    name        VARCHAR(100) NOT NULL,
    icon        VARCHAR(50)  NULL,
    parent_id   INT UNSIGNED NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    CONSTRAINT fk_category_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_category_parent   FOREIGN KEY (parent_id)   REFERENCES categories(id) ON DELETE CASCADE,
    KEY idx_category_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    title       VARCHAR(200) NOT NULL,
    slug        VARCHAR(200) NOT NULL,
    kind        ENUM('note','entity') NOT NULL DEFAULT 'entity',
    body_html   MEDIUMTEXT   NULL,
    body_json   MEDIUMTEXT   NULL,
    created_by  INT UNSIGNED NULL,
    updated_by  INT UNSIGNED NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_campaign_slug (campaign_id, slug),
    KEY idx_page_campaign (campaign_id),
    KEY idx_page_category (category_id),
    CONSTRAINT fk_page_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_page_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_meta (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id    INT UNSIGNED NOT NULL,
    meta_key   VARCHAR(100) NOT NULL,
    meta_value TEXT         NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    KEY idx_meta_page (page_id),
    CONSTRAINT fk_meta_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS links (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id    INT UNSIGNED NOT NULL,
    source_page_id INT UNSIGNED NOT NULL,
    target_page_id INT UNSIGNED NULL,
    target_title   VARCHAR(200) NOT NULL,
    KEY idx_link_source (source_page_id),
    KEY idx_link_target (target_page_id),
    KEY idx_link_target_title (campaign_id, target_title),
    CONSTRAINT fk_link_campaign FOREIGN KEY (campaign_id)    REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_link_source   FOREIGN KEY (source_page_id) REFERENCES pages(id)     ON DELETE CASCADE,
    CONSTRAINT fk_link_target   FOREIGN KEY (target_page_id) REFERENCES pages(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Collapsible body sections ("chapters") of a page, each its own rich-text block.
CREATE TABLE IF NOT EXISTS page_sections (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(200) NOT NULL,
    body_html  MEDIUMTEXT   NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    KEY idx_section_page (page_id),
    CONSTRAINT fk_section_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Free-form (optionally hierarchical, e.g. "quest/open-thread") tags on pages.
CREATE TABLE IF NOT EXISTS page_tags (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    page_id     INT UNSIGNED NOT NULL,
    tag         VARCHAR(80)  NOT NULL,
    KEY idx_tag_campaign (campaign_id, tag),
    KEY idx_tag_page (page_id),
    CONSTRAINT fk_tag_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_tag_page     FOREIGN KEY (page_id)     REFERENCES pages(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Typed field templates attached to a category (NPC, Place, Item, ...).
-- A page inherits its category's fields, falling back to the parent category.
CREATE TABLE IF NOT EXISTS category_fields (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    label       VARCHAR(100) NOT NULL,
    field_key   VARCHAR(100) NOT NULL,
    type        VARCHAR(20)  NOT NULL DEFAULT 'text',
    options     TEXT         NULL,      -- JSON array of choices (select / suggested)
    sort_order  INT          NOT NULL DEFAULT 0,
    KEY idx_field_category (category_id),
    CONSTRAINT fk_field_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_field_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_revisions (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id   INT UNSIGNED NOT NULL,
    title     VARCHAR(200) NOT NULL,
    body_html MEDIUMTEXT   NULL,
    body_json MEDIUMTEXT   NULL,
    edited_by INT UNSIGNED NULL,
    edited_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rev_page (page_id),
    CONSTRAINT fk_rev_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
