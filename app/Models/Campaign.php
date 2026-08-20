<?php

declare(strict_types=1);

namespace App\Models;

use App\Lib\Db;

final class Campaign
{
    /** Create a campaign, make the creator its GM, and seed default categories. */
    public static function create(string $name, string $description, int $userId): int
    {
        $name = trim($name) !== '' ? trim($name) : 'Untitled Campaign';
        $code = self::freshInviteCode();

        Db::run(
            'INSERT INTO campaigns (name, description, invite_code, created_by) VALUES (?, ?, ?, ?)',
            [$name, trim($description), $code, $userId]
        );
        $id = (int) Db::conn()->lastInsertId();

        Db::run(
            'INSERT INTO campaign_members (campaign_id, user_id, role) VALUES (?, ?, ?)',
            [$id, $userId, 'gm']
        );

        Category::seedDefaults($id);
        Template::seedCampaign($id);
        return $id;
    }

    public static function find(int $id): ?array
    {
        $row = Db::run('SELECT * FROM campaigns WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    public static function findByInvite(string $code): ?array
    {
        $row = Db::run('SELECT * FROM campaigns WHERE invite_code = ?', [strtoupper(trim($code))])->fetch();
        return $row ?: null;
    }

    /** Campaigns the given user belongs to, newest activity first. */
    public static function forUser(int $userId): array
    {
        return Db::run(
            'SELECT c.*, m.role,
                    (SELECT COUNT(*) FROM pages p WHERE p.campaign_id = c.id) AS page_count
             FROM campaigns c
             JOIN campaign_members m ON m.campaign_id = c.id
             WHERE m.user_id = ?
             ORDER BY c.created_at DESC',
            [$userId]
        )->fetchAll();
    }

    public static function isMember(int $campaignId, int $userId): bool
    {
        return (bool) Db::run(
            'SELECT 1 FROM campaign_members WHERE campaign_id = ? AND user_id = ?',
            [$campaignId, $userId]
        )->fetch();
    }

    public static function join(int $campaignId, int $userId, string $role = 'player'): void
    {
        Db::run(
            'INSERT IGNORE INTO campaign_members (campaign_id, user_id, role) VALUES (?, ?, ?)',
            [$campaignId, $userId, $role]
        );
    }

    public static function members(int $campaignId): array
    {
        return Db::run(
            'SELECT u.id, u.username, m.role, m.joined_at
             FROM campaign_members m
             JOIN users u ON u.id = m.user_id
             WHERE m.campaign_id = ?
             ORDER BY m.role DESC, u.username',
            [$campaignId]
        )->fetchAll();
    }

    private static function freshInviteCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        } while (self::findByInvite($code) !== null);
        return $code;
    }
}
