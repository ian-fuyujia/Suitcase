<?php

if (!function_exists('sfTableExists')) {
    function sfTableExists($conn, $tableName) {
        if (!$conn) {
            return false;
        }
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$tableName);
        if ($safe === '') {
            return false;
        }
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('sfTableColumns')) {
    function sfTableColumns($conn, $tableName) {
        $cols = [];
        if (!$conn) {
            return $cols;
        }
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$tableName);
        if ($safe === '') {
            return $cols;
        }
        $res = $conn->query("SHOW COLUMNS FROM `{$safe}`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = $row['Field'];
            }
        }
        return $cols;
    }
}

if (!function_exists('sfProductImageOrder')) {
    function sfProductImageOrder($conn, $alias = 'pi') {
        $prefix = $alias !== '' ? preg_replace('/[^a-zA-Z0-9_]/', '', $alias) . '.' : '';
        $cols = sfTableColumns($conn, 'product_images');
        $parts = [];

        if (in_array('is_main', $cols, true)) {
            $parts[] = $prefix . 'is_main DESC';
        }
        if (in_array('sort_order', $cols, true)) {
            $parts[] = $prefix . 'sort_order ASC';
        } elseif (in_array('display_order', $cols, true)) {
            $parts[] = $prefix . 'display_order ASC';
        }
        if (in_array('image_id', $cols, true)) {
            $parts[] = $prefix . 'image_id ASC';
        }

        return !empty($parts) ? implode(', ', $parts) : $prefix . 'product_id ASC';
    }
}

if (!function_exists('sfCategoryUrl')) {
    function sfCategoryUrl($categoryId) {
        return 'new_in.php?category_id=' . intval($categoryId);
    }
}

if (!function_exists('sfPublicFileExists')) {
    function sfPublicFileExists($relativePath) {
        $relativePath = ltrim((string)$relativePath, '/\\');
        if ($relativePath === '' || strpos($relativePath, '..') !== false) {
            return false;
        }

        return is_file(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
    }
}

if (!function_exists('sfFetchCategories')) {
    function sfFetchCategories($conn) {
        $categories = [];
        if (!sfTableExists($conn, 'categories')) {
            return $categories;
        }

        $cols = sfTableColumns($conn, 'categories');
        $parentSelect = in_array('parent_id', $cols, true) ? 'parent_id' : 'NULL AS parent_id';
        $sql = "SELECT category_id, name, {$parentSelect}
                FROM categories
                ORDER BY COALESCE(parent_id, 0) ASC, name ASC";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $categories[] = $row;
            }
        }
        return $categories;
    }
}

if (!function_exists('sfFetchHomepageBanners')) {
    function sfFetchHomepageBanners($conn, $limit = 5) {
        $banners = [];
        if (!sfTableExists($conn, 'promotion_banners') || !sfTableExists($conn, 'promotions')) {
            return $banners;
        }

        $limit = max(1, intval($limit));
        $sql = "
            SELECT
                pb.promotion_id,
                pb.banner_image_url,
                p.promotion_image_url,
                p.name,
                p.description,
                p.start_at,
                p.end_at
            FROM promotion_banners pb
            INNER JOIN promotions p ON p.id = pb.promotion_id
            WHERE pb.is_show_on_homepage = 1
              AND p.is_active = 1
              AND NOW() BETWEEN p.start_at AND p.end_at
            ORDER BY pb.sort_order ASC, p.start_at DESC, pb.promotion_id DESC
            LIMIT {$limit}
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (!sfPublicFileExists($row['banner_image_url'])) {
                    $row['banner_image_url'] = sfPublicFileExists($row['promotion_image_url'])
                        ? $row['promotion_image_url']
                        : '';
                }
                $banners[] = $row;
            }
        }
        return $banners;
    }
}

if (!function_exists('sfColorHex')) {
    function sfColorHex($color) {
        $normalized = strtolower(trim((string)$color));
        $map = [
            'black' => '#111827', 'white' => '#f8fafc', 'gray' => '#6b7280',
            'grey' => '#6b7280', 'silver' => '#c0c4cc', 'red' => '#dc2626',
            'blue' => '#1d4ed8', 'green' => '#4d7c0f', 'yellow' => '#facc15',
            'orange' => '#f97316', 'pink' => '#f9a8d4', 'purple' => '#7c3aed',
            'brown' => '#92400e', 'beige' => '#e7d8bf',
            '黑' => '#111827', '黑色' => '#111827', '白' => '#f8fafc', '白色' => '#f8fafc',
            '灰' => '#6b7280', '灰色' => '#6b7280', '銀' => '#c0c4cc', '銀色' => '#c0c4cc',
            '紅' => '#dc2626', '紅色' => '#dc2626', '藍' => '#1d4ed8', '藍色' => '#1d4ed8',
            '綠' => '#4d7c0f', '綠色' => '#4d7c0f', '黃' => '#facc15', '黃色' => '#facc15',
            '橘' => '#f97316', '橘色' => '#f97316', '粉' => '#f9a8d4', '粉色' => '#f9a8d4',
            '紫' => '#7c3aed', '紫色' => '#7c3aed', '棕' => '#92400e', '棕色' => '#92400e',
            '咖啡' => '#78350f', '咖啡色' => '#78350f', '米' => '#e7d8bf', '米色' => '#e7d8bf',
        ];

        return $map[$normalized] ?? null;
    }
}

if (!function_exists('sfValidHexColor')) {
    function sfValidHexColor($hex) {
        $hex = trim((string)$hex);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) ? strtoupper($hex) : '';
    }
}

if (!function_exists('sfSplitMetaList')) {
    function sfSplitMetaList($value) {
        $items = array_filter(array_map('trim', explode('||', (string)$value)), function ($item) {
            return $item !== '';
        });
        return array_values(array_unique($items));
    }
}

if (!function_exists('sfNormalizeSizeLabel')) {
    function sfNormalizeSizeLabel($size) {
        $size = trim((string)$size);
        if ($size === '') {
            return '';
        }
        $size = preg_replace('/\s+/', '', $size);
        return preg_match('/吋$/u', $size) ? preg_replace('/(吋)+$/u', '吋', $size) : $size . '吋';
    }
}

if (!function_exists('sfDecodeHexText')) {
    function sfDecodeHexText($hex) {
        $hex = (string)$hex;
        if ($hex === '' || !ctype_xdigit($hex) || strlen($hex) % 2 !== 0) {
            return '';
        }
        $value = hex2bin($hex);
        return $value === false ? '' : $value;
    }
}

if (!function_exists('sfProductCardVariantSelectSql')) {
    function sfProductCardVariantSelectSql($conn, $alias = 'v', $isMemberPriceEligible = false) {
        $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        $prefix = $safeAlias !== '' ? $safeAlias . '.' : '';
        $priceSql = function_exists('apVariantPriceSql') ? apVariantPriceSql($safeAlias, $isMemberPriceEligible) : "COALESCE({$prefix}special_price, {$prefix}original_price, 0)";
        $variantColumns = sfTableColumns($conn, 'product_variants');
        $colorHexSql = in_array('color_hex', $variantColumns, true) ? "HEX(COALESCE({$prefix}color_hex, ''))" : "''";

        return "
            GROUP_CONCAT(
                CONCAT_WS(':',
                    COALESCE({$prefix}variant_id, 0),
                    COALESCE({$prefix}original_price, 0),
                    IFNULL({$prefix}special_price, ''),
                    COALESCE({$prefix}member_price, 0),
                    COALESCE({$prefix}stock_available, 0),
                    HEX(COALESCE({$prefix}color, '')),
                    {$colorHexSql},
                    HEX(COALESCE({$prefix}size_inches, '')),
                    {$priceSql}
                )
                ORDER BY
                    CASE WHEN COALESCE({$prefix}stock_available, 0) > 0 THEN 0 ELSE 1 END,
                    {$priceSql} ASC,
                    COALESCE({$prefix}variant_id, 0) ASC
                SEPARATOR '||'
            ) AS card_variants
        ";
    }
}

if (!function_exists('sfProductCardVariants')) {
    function sfProductCardVariants(array $product) {
        $rawRows = sfSplitMetaList($product['card_variants'] ?? '');
        $variants = [];
        foreach ($rawRows as $rawRow) {
            $parts = explode(':', $rawRow);
            if (count($parts) < 9) {
                continue;
            }
            $variants[] = [
                'variant_id' => (int)$parts[0],
                'original_price' => (float)$parts[1],
                'special_price' => $parts[2] === '' ? null : (float)$parts[2],
                'member_price' => (float)$parts[3],
                'stock_available' => (int)$parts[4],
                'color' => sfDecodeHexText($parts[5]),
                'color_hex' => sfValidHexColor(sfDecodeHexText($parts[6])),
                'size_inches' => sfDecodeHexText($parts[7]),
                'display_price' => (float)$parts[8],
            ];
        }
        return $variants;
    }
}

if (!function_exists('sfPickProductCardVariant')) {
    function sfPickProductCardVariant(array $variants) {
        $best = null;
        foreach ($variants as $variant) {
            if ($best === null) {
                $best = $variant;
                continue;
            }
            $variantInStock = (int)$variant['stock_available'] > 0;
            $bestInStock = (int)$best['stock_available'] > 0;
            if ($variantInStock !== $bestInStock) {
                if ($variantInStock) {
                    $best = $variant;
                }
                continue;
            }
            if ((float)$variant['display_price'] < (float)$best['display_price']) {
                $best = $variant;
                continue;
            }
            if ((float)$variant['display_price'] === (float)$best['display_price'] && (int)$variant['variant_id'] < (int)$best['variant_id']) {
                $best = $variant;
            }
        }
        return $best;
    }
}

if (!function_exists('sfProductCardMeta')) {
    function sfProductCardMeta(array $product, $isMemberPriceEligible = false) {
        $variants = sfProductCardVariants($product);
        $variantCount = count($variants);
        $representative = sfPickProductCardVariant($variants);

        if (!$representative) {
            $representative = [
                'display_price' => (float)($product['display_price'] ?? $product['price'] ?? 0),
                'original_price' => (float)($product['original_price'] ?? 0),
                'special_price' => isset($product['special_price']) && $product['special_price'] !== null ? (float)$product['special_price'] : null,
                'member_price' => isset($product['member_price']) && $product['member_price'] !== null ? (float)$product['member_price'] : null,
                'stock_available' => (int)($product['total_stock'] ?? 0),
                'color' => '',
                'color_hex' => '',
                'size_inches' => '',
            ];
            $variantCount = (int)($product['variant_count'] ?? 0);
        }

        $colors = [];
        $sizes = [];
        $totalStock = 0;
        $hasAnySpecial = false;
        foreach ($variants as $variant) {
            $totalStock += max(0, (int)$variant['stock_available']);
            $colorName = trim((string)$variant['color']);
            if ($colorName !== '') {
                $colorHex = sfValidHexColor($variant['color_hex']);
                if ($colorHex !== '') {
                    $colors[$colorName] = [
                        'name' => $colorName,
                        'hex' => $colorHex,
                    ];
                }
            }
            $sizeLabel = sfNormalizeSizeLabel($variant['size_inches']);
            if ($sizeLabel !== '') {
                $sizes[$sizeLabel] = $sizeLabel;
            }
            $special = $variant['special_price'];
            $original = (float)$variant['original_price'];
            if ($special !== null && $special > 0 && ($original <= 0 || $special < $original)) {
                $hasAnySpecial = true;
            }
        }

        if ($totalStock === 0) {
            $totalStock = (int)($product['total_stock'] ?? 0);
        }

        $display = (float)$representative['display_price'];
        $original = (float)$representative['original_price'];
        $special = $representative['special_price'];
        $member = $representative['member_price'];
        $suffix = $variantCount > 1 ? '起' : '';
        $label = '';
        if ($isMemberPriceEligible && $member !== null && $member > 0 && $display <= (float)$member && ($original <= 0 || (float)$member < $original)) {
            $label = '會員價' . $suffix;
        } elseif ($special !== null && $special > 0 && $display <= (float)$special && ($original <= 0 || (float)$special < $original)) {
            $label = '特價' . $suffix;
        } elseif ($hasAnySpecial) {
            $label = '部分規格優惠';
        }

        return [
            'representative' => $representative,
            'variant_count' => $variantCount,
            'colors' => array_values($colors),
            'sizes' => array_values($sizes),
            'total_stock' => $totalStock,
            'price' => [
                'display' => $display,
                'original' => $original,
                'label' => $label,
                'suffix' => $suffix,
                'has_discount' => $original > 0 && $display > 0 && $display < $original,
            ],
        ];
    }
}

if (!function_exists('sfRenderProductCard')) {
    function sfRenderProductCard(array $product, $isMemberPriceEligible = false, $extraClass = '') {
        $productId = (int)($product['product_id'] ?? 0);
        $name = htmlspecialchars((string)($product['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $imageUrl = trim((string)($product['image_url'] ?? ''));
        $imageSrc = $imageUrl !== '' ? '../' . ltrim($imageUrl, '/') : '';
        $meta = sfProductCardMeta($product, $isMemberPriceEligible);
        $colors = $meta['colors'];
        $sizes = $meta['sizes'];
        $priceMeta = $meta['price'];
        $totalStock = (int)$meta['total_stock'];
        $href = 'product_detail.php?id=' . $productId;
        $classes = trim('product-card product-card-rich ' . $extraClass);
        $stockLabel = '售罄';
        $stockClass = 'is-empty';
        if ($totalStock > 3) {
            $stockLabel = '現貨';
            $stockClass = 'is-ready';
        } elseif ($totalStock > 0) {
            $stockLabel = '低庫存 ' . $totalStock;
            $stockClass = 'is-low';
        }

        ob_start();
        ?>
        <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo htmlspecialchars($classes, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="product-img-wrapper">
                <?php if ($imageSrc !== ''): ?>
                    <img src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>" class="product-img" alt="<?php echo $name; ?>" loading="lazy">
                <?php else: ?>
                    <div class="product-img product-img-placeholder">No Img</div>
                <?php endif; ?>
                <?php if ($priceMeta['label'] !== ''): ?>
                    <span class="product-badge-sale"><?php echo htmlspecialchars($priceMeta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if (!empty($sizes)): ?>
                    <span class="product-size-badge"><?php echo htmlspecialchars(implode(' / ', array_slice($sizes, 0, 3)), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <?php if (!empty($colors)): ?>
                    <div class="product-swatches" aria-label="可選顏色">
                        <?php foreach (array_slice($colors, 0, 6) as $color): ?>
                            <?php $hex = sfValidHexColor($color['hex'] ?? ''); ?>
                            <span class="product-swatch<?php echo $hex === '#F8FAFC' ? ' is-light' : ''; ?>" title="<?php echo htmlspecialchars($color['name'], ENT_QUOTES, 'UTF-8'); ?>" style="<?php echo $hex ? 'background:' . htmlspecialchars($hex, ENT_QUOTES, 'UTF-8') . ';' : ''; ?>">
                                <?php
                                $fallbackInitial = function_exists('mb_substr') ? mb_substr($color['name'], 0, 1, 'UTF-8') : substr($color['name'], 0, 1);
                                echo $hex ? '' : htmlspecialchars($fallbackInitial, ENT_QUOTES, 'UTF-8');
                                ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if (count($colors) > 6): ?>
                            <span class="product-swatch-more">+<?php echo count($colors) - 6; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="product-title"><?php echo $name; ?></div>
                <div class="product-price-row">
                    <span class="product-price">NT$ <?php echo number_format($priceMeta['display']); ?><?php echo htmlspecialchars($priceMeta['suffix'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($priceMeta['has_discount']): ?>
                        <span class="product-original-price">NT$ <?php echo number_format($priceMeta['original']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="product-card-meta">
                    <span class="<?php echo htmlspecialchars($stockClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($stockLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (!empty($sizes)): ?>
                        <span><?php echo htmlspecialchars(count($sizes) . ' 種尺寸', ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php
        return trim(ob_get_clean());
    }
}
