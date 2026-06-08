<?php

if (!function_exists('apSettingsTableExists')) {
    function apSettingsTableExists($conn) {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $res = $conn->query("SHOW TABLES LIKE 'system_settings'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('apVipDefaultThresholds')) {
    function apVipDefaultThresholds() {
        return [
            '2' => 10000,
            '3' => 30000,
        ];
    }
}

if (!function_exists('apVipThresholds')) {
    function apVipThresholds($conn = null) {
        $thresholds = apVipDefaultThresholds();
        if (!apSettingsTableExists($conn)) {
            return $thresholds;
        }

        $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('vip_threshold', 'vvip_threshold')");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $value = max(0, (int)($row['setting_value'] ?? 0));
                if ($row['setting_key'] === 'vip_threshold' && $value > 0) {
                    $thresholds['2'] = $value;
                }
                if ($row['setting_key'] === 'vvip_threshold' && $value > 0) {
                    $thresholds['3'] = $value;
                }
            }
        }

        if ($thresholds['3'] <= $thresholds['2']) {
            $thresholds['3'] = max($thresholds['2'] + 1, apVipDefaultThresholds()['3']);
        }
        return $thresholds;
    }
}

if (!function_exists('apSaveVipThresholds')) {
    function apSaveVipThresholds($conn, $vipThreshold, $vvipThreshold) {
        if (!($conn instanceof mysqli) || !apSettingsTableExists($conn)) {
            return false;
        }

        $vipThreshold = max(0, (int)$vipThreshold);
        $vvipThreshold = max(0, (int)$vvipThreshold);
        if ($vipThreshold <= 0 || $vvipThreshold <= $vipThreshold) {
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        if (!$stmt) {
            return false;
        }

        $ok = true;
        foreach (['vip_threshold' => $vipThreshold, 'vvip_threshold' => $vvipThreshold] as $key => $value) {
            $value = (string)$value;
            $stmt->bind_param('ss', $key, $value);
            if (!$stmt->execute()) {
                $ok = false;
                break;
            }
        }
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('apMembershipLevelName')) {
    function apMembershipLevelName($level) {
        $level = trim((string)$level);
        if ($level === '3') {
            return 'VVIP';
        }
        if ($level === '2') {
            return 'VIP';
        }
        return '一般會員';
    }
}

if (!function_exists('apMembershipLevelRank')) {
    function apMembershipLevelRank($level) {
        $level = trim((string)$level);
        return in_array($level, ['1', '2', '3'], true) ? (int)$level : 1;
    }
}

if (!function_exists('apMembershipSpendWhereSql')) {
    function apMembershipSpendWhereSql($conn, $ordersAlias = 'o') {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$ordersAlias);
        $prefix = $alias !== '' ? $alias . '.' : '';
        $where = "{$prefix}status <> 'CANCELLED'";

        $returnTable = $conn instanceof mysqli ? $conn->query("SHOW TABLES LIKE 'return_requests'") : false;
        if ($returnTable && $returnTable->num_rows > 0) {
            $where .= " AND NOT EXISTS (
                SELECT 1
                FROM return_requests rr
                WHERE rr.order_id = {$prefix}order_id
                  AND rr.status = 'REFUNDED'
            )";
        }

        return $where;
    }
}

if (!function_exists('apMembershipLevelForSpend')) {
    function apMembershipLevelForSpend($spend, $conn = null) {
        $spend = max(0, (float)$spend);
        $thresholds = apVipThresholds($conn);
        if ($spend >= $thresholds['3']) {
            return '3';
        }
        if ($spend >= $thresholds['2']) {
            return '2';
        }
        return '1';
    }
}

if (!function_exists('apMembershipSpend')) {
    function apMembershipSpend($conn, $userId) {
        if (!($conn instanceof mysqli) || (int)$userId <= 0) {
            return 0.0;
        }

        $where = apMembershipSpendWhereSql($conn, 'o');
        $stmt = $conn->prepare("SELECT COALESCE(SUM(o.total_amount), 0) AS spend FROM orders o WHERE o.user_id = ? AND {$where}");
        if (!$stmt) {
            return 0.0;
        }
        $userId = (int)$userId;
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float)($row['spend'] ?? 0);
    }
}

if (!function_exists('apMembershipProgress')) {
    function apMembershipProgress($level, $spend, $conn = null) {
        $thresholds = apVipThresholds($conn);
        $level = (string)$level;
        $spend = max(0, (float)$spend);

        if ($level === '3' || $spend >= $thresholds['3']) {
            return [
                'current_level' => '3',
                'current_label' => 'VVIP',
                'next_level' => null,
                'next_label' => '',
                'threshold' => $thresholds['3'],
                'remaining' => 0,
                'percent' => 100,
            ];
        }

        $nextLevel = $spend >= $thresholds['2'] || $level === '2' ? '3' : '2';
        $threshold = $thresholds[$nextLevel];
        $percent = $threshold > 0 ? min(100, (int)floor(($spend / $threshold) * 100)) : 100;

        return [
            'current_level' => $level !== '' ? $level : '1',
            'current_label' => apMembershipLevelName($level),
            'next_level' => $nextLevel,
            'next_label' => apMembershipLevelName($nextLevel),
            'threshold' => $threshold,
            'remaining' => max(0, $threshold - $spend),
            'percent' => $percent,
        ];
    }
}

if (!function_exists('apApplyMembershipUpgrade')) {
    function apApplyMembershipUpgrade($conn, $userId) {
        if (!($conn instanceof mysqli) || (int)$userId <= 0) {
            return ['upgraded' => false, 'level' => '1', 'spend' => 0.0];
        }

        $userId = (int)$userId;
        $spend = apMembershipSpend($conn, $userId);
        $targetLevel = apMembershipLevelForSpend($spend, $conn);

        $stmt = $conn->prepare("SELECT COALESCE(NULLIF(membership_level, ''), '1') AS membership_level FROM users WHERE user_id = ? LIMIT 1");
        if (!$stmt) {
            return ['upgraded' => false, 'level' => $targetLevel, 'spend' => $spend];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $currentLevel = $row['membership_level'] ?? '1';
        if (apMembershipLevelRank($targetLevel) <= apMembershipLevelRank($currentLevel)) {
            return ['upgraded' => false, 'level' => $currentLevel, 'spend' => $spend];
        }

        $update = $conn->prepare('UPDATE users SET membership_level = ? WHERE user_id = ?');
        if (!$update) {
            return ['upgraded' => false, 'level' => $currentLevel, 'spend' => $spend];
        }
        $update->bind_param('si', $targetLevel, $userId);
        $update->execute();
        $ok = $update->affected_rows >= 0;
        $update->close();

        return ['upgraded' => $ok, 'level' => $ok ? $targetLevel : $currentLevel, 'spend' => $spend];
    }
}

if (!function_exists('apSyncMembershipUpgrades')) {
    function apSyncMembershipUpgrades($conn) {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $thresholds = apVipThresholds($conn);
        $where = apMembershipSpendWhereSql($conn, 'o');
        $vipThreshold = (int)$thresholds['2'];
        $vvipThreshold = (int)$thresholds['3'];
        $sql = "
            UPDATE users u
            JOIN (
                SELECT o.user_id, COALESCE(SUM(o.total_amount), 0) AS spend
                FROM orders o
                WHERE {$where}
                GROUP BY o.user_id
            ) s ON s.user_id = u.user_id
            SET u.membership_level = CASE
                WHEN s.spend >= {$vvipThreshold} AND COALESCE(NULLIF(u.membership_level, ''), '1') IN ('1', '2') THEN '3'
                WHEN s.spend >= {$vipThreshold} AND COALESCE(NULLIF(u.membership_level, ''), '1') = '1' THEN '2'
                ELSE COALESCE(NULLIF(u.membership_level, ''), '1')
            END
            WHERE
                (s.spend >= {$vvipThreshold} AND COALESCE(NULLIF(u.membership_level, ''), '1') IN ('1', '2'))
                OR (s.spend >= {$vipThreshold} AND COALESCE(NULLIF(u.membership_level, ''), '1') = '1')
        ";

        return (bool)$conn->query($sql);
    }
}
