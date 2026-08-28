<?php

declare(strict_types=1);

namespace App\Models;

use App\Lib\Db;
use App\Lib\Slug;

/**
 * Typed field templates per category. A page shows the fields of its category,
 * inheriting from the parent category when the sub-category defines none.
 *
 * Field types: text, textarea, select (fixed dropdown), suggest (dropdown that
 * learns previously-used values), image (upload), date, multi (comma-separated).
 * Values are stored in page_meta keyed by field_key.
 */
final class Template
{
    public const TYPES = ['text', 'textarea', 'select', 'suggest', 'image', 'date', 'multi', 'user', 'link'];

    /**
     * Default field sets keyed by (lower-cased) category name, seeded on campaign
     * creation and loadable on demand. options => choices for select/suggest.
     */
    private const DEFAULTS = [
        'party' => [
            ['Image', 'image'],
            ['Nickname', 'text'],
            ['Player', 'user'],
            ['Class', 'suggest', ['Barbarian','Bard','Cleric','Druid','Fighter','Monk','Paladin','Ranger','Rogue','Sorcerer','Warlock','Wizard','Artificer']],
            ['Race', 'suggest', ['Human','Elf','Half-Elf','Dwarf','Halfling','Gnome','Half-Orc','Tiefling','Dragonborn']],
            ['Level', 'text'],
            ['Status', 'select', ['Alive','Dead','Missing','Unknown']],
        ],
        'npcs' => [
            ['Image', 'image'],
            ['Nickname', 'text'],
            ['Gender', 'select', ['Male','Female','Non-binary','Unknown']],
            ['Race', 'suggest', ['Human','Elf','Half-Elf','Dwarf','Halfling','Gnome','Half-Orc','Tiefling','Dragonborn']],
            ['Age range', 'select', ['Child','Adolescent','Young adult','Adult','Middle-aged','Elderly','Ancient','Unknown']],
            ['Status', 'select', ['Alive','Dead','Undead','Missing','Unknown']],
            ['Occupation', 'text'],
            ['Location', 'link', ['Places', 'Points of Interest']],
            ['Faction', 'link', ['Organizations']],
        ],
        'quests' => [
            ['Quest giver', 'link', ['NPCs']],
            ['Reward', 'text'],
            ['Status', 'select', ['Active','Open thread','Completed']],
            ['Started (session)', 'link', ['Sessions']],
            ['Completed (session)', 'link', ['Sessions']],
        ],
        'organizations' => [
            ['Image', 'image'],
            ['Type', 'suggest', ['Guild','Cult','Noble house','Order','Criminal','Military','Merchant']],
            ['Leader', 'link', ['NPCs']],
            ['Headquarters', 'link', ['Places']],
            ['Status', 'select', ['Active','Disbanded','Hidden','Unknown']],
            ['Goal', 'textarea'],
        ],
        'places' => [
            ['Image', 'image'],
            ['Type', 'suggest', ['City','Town','Village','Castle','Fort','Dungeon','Ruin','Forest','Cave','Temple']],
            ['Region', 'link', ['Places']],
            ['Population', 'text'],
            ['Ruler', 'link', ['NPCs']],
            ['Status', 'select', ['Thriving','Struggling','Abandoned','Destroyed','Unknown']],
        ],
        'points of interest' => [
            ['Image', 'image'],
            ['Type', 'suggest', ['Landmark','Shop','Tavern','Shrine','Monument','Natural','Other']],
            ['Region', 'link', ['Places']],
            ['Notable for', 'textarea'],
        ],
        'items' => [
            ['Image', 'image'],
            ['Type', 'suggest', ['Weapon','Armor','Potion','Scroll','Ring','Wand','Rod','Staff','Wondrous item']],
            ['Rarity', 'select', ['Common','Uncommon','Rare','Very rare','Legendary','Artifact']],
            ['Attunement', 'select', ['No','Yes']],
            ['Owner', 'link', ['Party']],
            ['Value', 'text'],
        ],
        'sessions' => [
            ['Session number', 'text'],
            ['Played on', 'date'],
            ['Note-taker', 'user'],
            ['Present', 'multi'],
            ['Recap', 'text'],
        ],
    ];

    /**
     * Default body sections ("chapters") per category. Each becomes its own
     * collapsible rich-text editor. Dutch, matching the user's vault style.
     */
    private const SECTIONS = [
        'party' => ['Achtergrond', 'Spells & trucs', 'Magische items', 'Memorabel'],
        'npcs' => ['Wie is het', 'Waar ontmoet', 'Wat die weet / wil', 'Ontmoeting', 'Gesprekken', 'Gebeurtenissen'],
        'organizations' => ['Wie zijn ze', 'Doel', 'Leden', 'Geschiedenis met de groep'],
        'places' => ['Beschrijving', 'Wie je hier vindt', 'Wat er gebeurd is'],
        'points of interest' => ['Beschrijving', 'Wat er gebeurd is'],
        'quests' => ['De opdracht', 'Voortgang', 'Nog te doen'],
        'items' => ['Beschrijving', 'Herkomst', 'Eigenschappen'],
        'sessions' => ['Verslag', 'Wie & wat', 'Buit'],
    ];

    /** Resolve the fields shown for a page in the given category (with inheritance). */
    public static function fieldsFor(int $campaignId, ?int $categoryId): array
    {
        $guard = 0;
        while ($categoryId !== null && $guard++ < 10) {
            $fields = self::rawFields($categoryId);
            if ($fields) {
                return $fields;
            }
            $parent = Db::run('SELECT parent_id FROM categories WHERE id = ?', [$categoryId])->fetch();
            $categoryId = $parent && $parent['parent_id'] !== null ? (int) $parent['parent_id'] : null;
        }
        return [];
    }

    /** Default section titles for a category, with inheritance. */
    public static function sectionTitles(int $campaignId, ?int $categoryId): array
    {
        $guard = 0;
        while ($categoryId !== null && $guard++ < 10) {
            $row = Db::run('SELECT name, parent_id FROM categories WHERE id = ?', [$categoryId])->fetch();
            if (!$row) {
                break;
            }
            $key = mb_strtolower(trim($row['name']));
            if (isset(self::SECTIONS[$key])) {
                return self::SECTIONS[$key];
            }
            $categoryId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }
        return [];
    }

    /** Fields defined directly on a category (no inheritance) — for the editor. */
    public static function rawFields(int $categoryId): array
    {
        $rows = Db::run(
            'SELECT * FROM category_fields WHERE category_id = ? ORDER BY sort_order, id',
            [$categoryId]
        )->fetchAll();
        foreach ($rows as &$r) {
            $r['options'] = $r['options'] ? (json_decode($r['options'], true) ?: []) : [];
        }
        return $rows;
    }

    /** Distinct values previously entered for a field across the campaign (learning dropdown). */
    public static function suggestions(int $campaignId, string $fieldKey): array
    {
        $rows = Db::run(
            'SELECT DISTINCT pm.meta_value
             FROM page_meta pm JOIN pages p ON p.id = pm.page_id
             WHERE p.campaign_id = ? AND pm.meta_key = ? AND pm.meta_value <> ""
             ORDER BY pm.meta_value',
            [$campaignId, $fieldKey]
        )->fetchAll();
        return array_column($rows, 'meta_value');
    }

    /**
     * Replace the field set for a category.
     * @param array<int,array{label:string,type:string,options:string}> $fields
     */
    public static function saveFields(int $campaignId, int $categoryId, array $fields): void
    {
        Db::run('DELETE FROM category_fields WHERE category_id = ? AND campaign_id = ?', [$categoryId, $campaignId]);
        $order = 0;
        foreach ($fields as $f) {
            $label = trim($f['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $type = in_array($f['type'] ?? 'text', self::TYPES, true) ? $f['type'] : 'text';
            $options = self::parseOptions($f['options'] ?? '');
            Db::run(
                'INSERT INTO category_fields (campaign_id, category_id, label, field_key, type, options, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$campaignId, $categoryId, $label, Slug::make($label), $type, $options ? json_encode($options) : null, $order++]
            );
        }
    }

    /** Seed a category's default field set if it currently has none. Returns true if seeded. */
    public static function seedForCategory(int $campaignId, int $categoryId, string $categoryName): bool
    {
        if (!self::hasDefaults($categoryName)) {
            return false;
        }
        if (self::rawFields($categoryId)) {
            return false; // don't clobber existing fields
        }
        self::insertDefaults($campaignId, $categoryId, $categoryName);
        return true;
    }

    /** Force a category's fields back to the defaults, replacing any existing ones. */
    public static function forceSeedForCategory(int $campaignId, int $categoryId, string $categoryName): bool
    {
        if (!self::hasDefaults($categoryName)) {
            return false;
        }
        Db::run('DELETE FROM category_fields WHERE category_id = ? AND campaign_id = ?', [$categoryId, $campaignId]);
        self::insertDefaults($campaignId, $categoryId, $categoryName);
        return true;
    }

    public static function hasDefaults(string $categoryName): bool
    {
        return isset(self::DEFAULTS[mb_strtolower(trim($categoryName))]);
    }

    private static function insertDefaults(int $campaignId, int $categoryId, string $categoryName): void
    {
        $order = 0;
        foreach (self::DEFAULTS[mb_strtolower(trim($categoryName))] as $def) {
            [$label, $type] = $def;
            $options = $def[2] ?? [];
            Db::run(
                'INSERT INTO category_fields (campaign_id, category_id, label, field_key, type, options, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$campaignId, $categoryId, $label, Slug::make($label), $type, $options ? json_encode($options) : null, $order++]
            );
        }
    }

    /** Seed defaults for every matching category in a campaign (used at creation + on demand). */
    public static function seedCampaign(int $campaignId): int
    {
        $seeded = 0;
        $cats = Db::run('SELECT id, name FROM categories WHERE campaign_id = ?', [$campaignId])->fetchAll();
        foreach ($cats as $c) {
            if (self::seedForCategory($campaignId, (int) $c['id'], $c['name'])) {
                $seeded++;
            }
        }
        return $seeded;
    }

    /** Total number of template fields defined across a campaign. */
    public static function countForCampaign(int $campaignId): int
    {
        return (int) Db::run(
            'SELECT COUNT(*) AS n FROM category_fields WHERE campaign_id = ?',
            [$campaignId]
        )->fetch()['n'];
    }

    /** Does this category (or an ancestor) resolve to the Sessions-style template? */
    public static function isSessionCategory(int $campaignId, ?int $categoryId): bool
    {
        $guard = 0;
        while ($categoryId !== null && $guard++ < 10) {
            $row = Db::run('SELECT name, parent_id FROM categories WHERE id = ?', [$categoryId])->fetch();
            if (!$row) {
                break;
            }
            if (mb_strtolower(trim($row['name'])) === 'sessions') {
                return true;
            }
            $categoryId = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        }
        return false;
    }

    /** Turn a newline- or comma-separated option string into a clean array. */
    private static function parseOptions(string $raw): array
    {
        $parts = preg_split('/[\r\n,]+/', $raw);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }
}
