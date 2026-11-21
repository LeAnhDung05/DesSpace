<?php
/**
 * DeSpace Multimodal-Ready AI Interior Design Concierge (Gemini 2.5 Flash RAG + Spatial Fit Engine).
 *
 * Queries the live WooCommerce catalog & 3D dimensions table to match exact customer room
 * clearance (Width x Depth x Height in cm), budget (VND), category, and finish color.
 * Uses Gemini 2.5 Flash grounded generation when `GEMINI_API_KEY` / `despace_gemini_api_key`
 * is configured, and falls back seamlessly to a deterministic Spatial & Policy Advisor.
 *
 * @package DeSpace\Catalog
 */

namespace DeSpace\Catalog;

use DeSpace\Product3D\Product3DModule;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConciergeService
 */
class ConciergeService {

	/**
	 * Process a customer question and return grounded reply + matched 3D-ready product cards.
	 *
	 * @param string $question Raw user message.
	 * @param string $lang     'vi' or 'en'.
	 * @return array{reply: string, products: array<int, array<string, mixed>>, mode: string}
	 */
	public static function answer( string $question, string $lang = 'vi' ): array {
		$is_en = ( 0 === strpos( strtolower( $lang ), 'en' ) );
		$q_raw = trim( $question );
		$q_norm = self::normalize_ascii( $q_raw );

		// 1. Fast Policy Intent Detection (Shipping / Payment / VietQR / Warranty / Order Tracking)
		if ( preg_match( '/\b(ship|shipping|delivery|giao hang|van chuyen|phi ship|lap dat)\b/', $q_norm ) && ! preg_match( '/\b(tu|ke|ban|ghe|sofa|giuong|cabinet|shelf|wardrobe)\b/', $q_norm ) ) {
			return array(
				'mode'     => 'policy',
				'products' => array(),
				'reply'    => $is_en
					? 'DeSpace provides nationwide insured delivery and white-glove installation across all 34 provinces/cities in Vietnam. Orders from 5,000,000 VND receive FREE shipping.'
					: 'DeSpace giao hàng có bảo hiểm và lắp đặt tận phòng trên toàn bộ 34 tỉnh/thành Việt Nam. Đơn hàng từ 5.000.000 ₫ được MIỄN PHÍ vận chuyển hoàn toàn.',
			);
		}

		if ( preg_match( '/\b(vietqr|chuyen khoan|thanh toan|payment|cod|tra gop|ngan hang)\b/', $q_norm ) && ! preg_match( '/\b(tu|ke|ban|ghe|sofa|giuong|cabinet|shelf|wardrobe)\b/', $q_norm ) ) {
			return array(
				'mode'     => 'policy',
				'products' => array(),
				'reply'    => $is_en
					? 'We support Cash on Delivery (COD) and instant Napas 24/7 VietQR Bank Transfer (auto-filled amount & order code DESPACE{ID} with real-time confirmation).'
					: 'DeSpace hỗ trợ Thanh toán khi nhận hàng (COD) và Chuyển khoản nhanh qua mã VietQR Napas 24/7 (tự động điền sẵn số tiền & nội dung DESPACE{MãĐơn}, đối soát tự động tức thì).',
			);
		}

		if ( preg_match( '/\b(bao hanh|doi tra|hoan tien|warranty|return|refund)\b/', $q_norm ) && ! preg_match( '/\b(tu|ke|ban|ghe|sofa|giuong|cabinet|shelf|wardrobe)\b/', $q_norm ) ) {
			return array(
				'mode'     => 'policy',
				'products' => array(),
				'reply'    => $is_en
					? 'Every DeSpace furniture piece carries a 5-year structural warranty and a 30-day worry-free return policy. Need immediate support? Reach us via Hotline/Zalo: 0332 174 590.'
					: 'Tất cả sản phẩm nội thất DeSpace đều được bảo hành kết cấu 5 năm và hỗ trợ đổi trả trong 30 ngày. Bạn có thể liên hệ nhanh qua Hotline/Zalo: 0332 174 590.',
			);
		}

		// 2. Parse Spatial Dimensions, Budget, Category & Finish Constraints
		$constraints = self::parse_constraints( $q_raw, $q_norm );

		// 3. Score & Filter Live WooCommerce Catalog + 3D Dimensions
		$catalog = self::load_live_catalog();
		$matched = self::rank_catalog( $catalog, $constraints, $q_norm );

		// 4. Optional Gemini 2.5 Flash Grounded Synthesis if API Key is configured
		$gemini_key = self::get_gemini_api_key();
		if ( '' !== $gemini_key ) {
			$ai_reply = self::call_gemini_grounded( $gemini_key, $q_raw, $is_en, $matched, $constraints );
			if ( null !== $ai_reply && '' !== $ai_reply ) {
				return array(
					'mode'     => 'gemini_rag',
					'reply'    => $ai_reply,
					'products' => array_slice( $matched, 0, 3 ),
				);
			}
		}

		// 5. Smart Deterministic Spatial & Interior Advisor Response
		if ( preg_match( '/^(hi|hello|xin chao|chao|alo|hey)(\b|$)/', $q_norm ) && empty( $constraints['has_specific_filter'] ) ) {
			return array(
				'mode'     => 'greeting',
				'products' => array(),
				'reply'    => $is_en
					? 'Hello! Tell me your room space (e.g. "width 120cm depth 35cm"), preferred wood finish, or budget, and I will match the exact 1:1 3D/AR furniture for you!'
					: 'Xin chào! Bạn có thể cho mình biết kích thước góc phòng (ví dụ: "ngang 1m2 sâu 35cm"), màu gỗ yêu thích hoặc tầm giá — mình sẽ đo khớp và gợi ý mẫu có sẵn mô hình 3D & AR chuẩn tỷ lệ 1:1 nhé!',
			);
		}

		if ( empty( $matched ) ) {
			$hint = '';
			if ( $constraints['max_width'] > 0 || $constraints['max_depth'] > 0 ) {
				$hint = $is_en
					? sprintf( ' (for max space %s)', self::format_constraint_summary( $constraints, true ) )
					: sprintf( ' (với giới hạn kích thước %s)', self::format_constraint_summary( $constraints, false ) );
			}
			return array(
				'mode'     => 'spatial_advisor',
				'products' => array(),
				'reply'    => $is_en
					? "I couldn't find an exact piece fitting those constraints{$hint}. Try relaxing the width/depth by 5–10 cm or ask for compact shoe cabinets, bookshelves, wardrobes, kitchen cabinets, or dressers."
					: "Hiện tại mình chưa tìm thấy mẫu nào vừa khít thông số này{$hint}. Bạn thử nới rộng thêm khoảng 5–10 cm hoặc cho mình biết loại tủ/kệ bạn đang ưu tiên nhé!",
			);
		}

		$top = array_slice( $matched, 0, 3 );
		$constraint_desc = self::format_constraint_summary( $constraints, $is_en );
		if ( '' !== $constraint_desc ) {
			$reply = $is_en
				? sprintf( 'Based on your criteria (%s), here are the best-fitting pieces with 1:1 3D & AR preview:', $constraint_desc )
				: sprintf( 'Dựa trên yêu cầu của bạn (%s), dưới đây là các mẫu vừa vặn nhất và có sẵn Xem 3D / Đặt thử AR 1:1 trong phòng:', $constraint_desc );
		} else {
			$reply = $is_en
				? 'Here are the best matching DeSpace designs (tap 3D/AR to test directly in your room):'
				: 'Mình gợi ý các thiết kế DeSpace phù hợp nhất cho không gian của bạn (bạn có thể bấm nút 3D/AR để ướm thử ngay vào phòng):';
		}

		return array(
			'mode'     => 'spatial_advisor',
			'reply'    => $reply,
			'products' => $top,
		);
	}

	/**
	 * Extract width, depth, height (in cm), budget (VND), category slug, and color keywords.
	 */
	public static function parse_constraints( string $raw, string $norm ): array {
		$max_w = 0.0;
		$max_d = 0.0;
		$max_h = 0.0;
		$max_price = 0.0;

		// Parse compact WxD or WxDxH like "114x33" or "120 x 35"
		if ( preg_match( '/(\d{2,3})\s*[x\*×]\s*(\d{2,3})(?:\s*[x\*×]\s*(\d{2,3}))?/u', $raw, $m ) ) {
			$max_w = floatval( $m[1] );
			$max_d = floatval( $m[2] );
			if ( ! empty( $m[3] ) ) {
				$max_h = floatval( $m[3] );
			}
		}

		// Parse Vietnamese/English width: "rộng 1m2", "ngang 1.2m", "ngang 120cm", "width 90"
		if ( preg_match( '/(?:rong|ngang|chieu rong|width)\s*(?:duoi|tầm|khoang|<=|<)?\s*(\d+(?:[\.,]\d+)?)\s*(m\d*|cm)?/i', $norm . ' ' . strtolower( $raw ), $mw ) ) {
			$max_w = self::parse_length_to_cm( $mw[1], $mw[2] ?? '', $raw );
		}
		// Special Vietnamese shorthand "1m2", "1m5" following rong/ngang
		if ( preg_match( '/(?:rong|ngang)\s*(?:duoi|tam|khoang)?\s*(\d)m(\d{1,2})\b/i', $norm, $msh ) ) {
			$digits = $msh[2];
			$max_w  = floatval( $msh[1] ) * 100.0 + ( strlen( $digits ) === 1 ? floatval( $digits ) * 10.0 : floatval( $digits ) );
		}

		// Depth: "sâu 35cm", "sâu 35", "depth 40"
		if ( preg_match( '/(?:sau|chieu sau|depth)\s*(?:duoi|tam|khoang|<=|<)?\s*(\d+(?:[\.,]\d+)?)\s*(m|cm)?/i', $norm, $md ) ) {
			$max_d = self::parse_length_to_cm( $md[1], $md[2] ?? 'cm', $raw );
		}

		// Height: "cao 1m8", "cao 90cm", "height 180"
		if ( preg_match( '/(?:cao|chieu cao|height)\s*(?:duoi|tam|khoang|<=|<)?\s*(\d)m(\d{1,2})\b/i', $norm, $mhh ) ) {
			$digits = $mhh[2];
			$max_h  = floatval( $mhh[1] ) * 100.0 + ( strlen( $digits ) === 1 ? floatval( $digits ) * 10.0 : floatval( $digits ) );
		} elseif ( preg_match( '/(?:cao|chieu cao|height)\s*(?:duoi|tam|khoang|<=|<)?\s*(\d+(?:[\.,]\d+)?)\s*(m|cm)?/i', $norm, $mh ) ) {
			$max_h = self::parse_length_to_cm( $mh[1], $mh[2] ?? 'cm', $raw );
		}

		// Budget: "dưới 2 triệu 5", "2.5 triệu", "3tr", "2500k", "2.500.000"
		if ( preg_match( '/(\d+)\s*(?:trieu|tr)\s*(\d{1,2})\b/i', $norm, $mp1 ) ) {
			$sub = strlen( $mp1[2] ) === 1 ? floatval( $mp1[2] ) * 100000 : floatval( $mp1[2] ) * 10000;
			$max_price = floatval( $mp1[1] ) * 1000000 + $sub;
		} elseif ( preg_match( '/(\d+(?:[\.,]\d+)?)\s*(?:trieu|tr|million|m\s*vnd)\b/i', strtolower( $raw ), $mp2 ) ) {
			$num = floatval( str_replace( ',', '.', $mp2[1] ) );
			$max_price = $num * 1000000;
		} elseif ( preg_match( '/(\d{3,4})\s*k\b/i', $norm, $mp3 ) ) {
			$max_price = floatval( $mp3[1] ) * 1000;
		} elseif ( preg_match( '/\b(\d{1,2}[\.\,]\d{3}[\.\,]\d{3})\b/', $raw, $mp4 ) ) {
			$max_price = floatval( preg_replace( '/\D/', '', $mp4[1] ) );
		}

		// Category mapping
		$cat_map = array(
			'shoe-racks-storage'          => array( 'tu giay', 'ke giay', 'de giay', 'shoe' ),
			'wardrobes'                   => array( 'tu quan ao', 'tu ao', 'wardrobe', 'closet' ),
			'bookshelves'                 => array( 'ke sach', 'tu sach', 'tu trung bay', 'bookshelf', 'bookcase' ),
			'kitchen-cabinets'            => array( 'tu bep', 'tu do kho', 'pantry', 'kitchen' ),
			'chest-of-drawers-sideboards' => array( 'tu ngan keo', 'tu dau giuong', 'sideboard', 'dresser', 'chest', 'drawer' ),
		);
		$matched_cat = '';
		foreach ( $cat_map as $slug => $terms ) {
			foreach ( $terms as $t ) {
				if ( false !== strpos( $norm, $t ) ) {
					$matched_cat = $slug;
					break 2;
				}
			}
		}

		// Finish / Color keywords
		$color_groups = array(
			'walnut' => array( 'oc cho', 'walnut', 'mocha', 'nau tram' ),
			'oak'    => array( 'soi', 'oak', 'sonoma', 'rustic', ' sang' ),
			'white'  => array( 'trang', 'white', 'ivory', 'kem' ),
			'grey'   => array( 'xam', 'slate', 'grey', 'gray', 'ghi' ),
			'wenge'  => array( 'wenge', 'nau den', 'den' ),
			'cherry' => array( 'cherry', 'anh dao', 'lome' ),
		);
		$matched_colors = array();
		foreach ( $color_groups as $ckey => $cterms ) {
			foreach ( $cterms as $ct ) {
				if ( false !== strpos( ' ' . $norm . ' ', $ct ) ) {
					$matched_colors[] = $ckey;
					break;
				}
			}
		}

		$has_specific = ( $max_w > 0 || $max_d > 0 || $max_h > 0 || $max_price > 0 || '' !== $matched_cat || ! empty( $matched_colors ) );

		return array(
			'max_width'           => $max_w,
			'max_depth'           => $max_d,
			'max_height'          => $max_h,
			'max_price'           => $max_price,
			'category'            => $matched_cat,
			'colors'              => $matched_colors,
			'has_specific_filter' => $has_specific,
		);
	}

	private static function parse_length_to_cm( string $num_str, string $unit, string $raw ): float {
		$val = floatval( str_replace( ',', '.', $num_str ) );
		$u   = strtolower( trim( $unit ) );
		if ( 'm' === $u || ( $val > 0 && $val < 4.5 && false !== stripos( $raw, 'm' ) ) ) {
			return $val * 100.0;
		}
		return $val;
	}

	private static function format_constraint_summary( array $c, bool $is_en ): string {
		$parts = array();
		if ( $c['max_width'] > 0 ) {
			$parts[] = ( $is_en ? 'Width ≤ ' : 'Rộng ≤ ' ) . (int) round( $c['max_width'] ) . ' cm';
		}
		if ( $c['max_depth'] > 0 ) {
			$parts[] = ( $is_en ? 'Depth ≤ ' : 'Sâu ≤ ' ) . (int) round( $c['max_depth'] ) . ' cm';
		}
		if ( $c['max_height'] > 0 ) {
			$parts[] = ( $is_en ? 'Height ≤ ' : 'Cao ≤ ' ) . (int) round( $c['max_height'] ) . ' cm';
		}
		if ( $c['max_price'] > 0 ) {
			$parts[] = ( $is_en ? 'Budget ≤ ' : 'Giá ≤ ' ) . number_format( (int) $c['max_price'], 0, ',', '.' ) . ' ₫';
		}
		return implode( ', ', $parts );
	}

	/**
	 * Load live published WooCommerce catalog with 3D/AR & physical dimensions.
	 */
	private static function load_live_catalog(): array {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$cache_key = 'despace_concierge_catalog_v1';
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$wc_items = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 60,
			)
		);

		$list = array();
		foreach ( $wc_items as $prod ) {
			$pid   = $prod->get_id();
			$terms = get_the_terms( $pid, 'product_cat' );
			$cats  = ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'slug' ) : array();

			$info_3d = function_exists( 'ax_get_product_3d_info' )
				? ax_get_product_3d_info( $pid )
				: Product3DModule::get_product_3d_info( $pid );

			$w = floatval( $info_3d['width_cm'] ?? $info_3d['width'] ?? $prod->get_length() ?: 0 );
			$d = floatval( $info_3d['depth_cm'] ?? $info_3d['depth'] ?? $prod->get_width() ?: 0 );
			$h = floatval( $info_3d['height_cm'] ?? $info_3d['height'] ?? $prod->get_height() ?: 0 );

			$price_num = (float) $prod->get_price();
			$dim_label = ( $w > 0 && $d > 0 && $h > 0 )
				? sprintf( '%s × %s × %s cm', (int) round( $w ), (int) round( $d ), (int) round( $h ) )
				: '';

			$en_title = (string) get_post_meta( $pid, '_despace_title_en', true );

			$list[] = array(
				'id'         => $pid,
				'sku'        => $prod->get_sku(),
				'name'       => wp_strip_all_tags( $prod->get_name() ),
				'name_en'    => $en_title,
				'price_num'  => $price_num,
				'price'      => html_entity_decode( wp_strip_all_tags( wc_price( $price_num ) ), ENT_QUOTES, 'UTF-8' ),
				'url'        => get_permalink( $pid ),
				'image'      => wp_get_attachment_image_url( $prod->get_image_id(), 'woocommerce_thumbnail' ) ?: '',
				'categories' => $cats,
				'width_cm'   => $w,
				'depth_cm'   => $d,
				'height_cm'  => $h,
				'dimensions' => $dim_label,
				'has_3d'     => ! empty( $info_3d['glb_url'] ),
				'ar_enabled' => ! empty( $info_3d['ar_enabled'] ),
				'glb_url'    => $info_3d['glb_url'] ?? '',
				'usdz_url'   => $info_3d['usdz_url'] ?? '',
			);
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( $cache_key, $list, 300 );
		}

		return $list;
	}

	/**
	 * Filter and score catalog items against spatial, budget, category, and keyword relevance.
	 */
	private static function rank_catalog( array $catalog, array $c, string $q_norm ): array {
		$stop_words = array( 'tim', 'cho', 'minh', 'toi', 'mau', 'nao', 'vua', 'voi', 'goc', 'phong', 'nha', 'cua', 'duoi', 'tam', 'khoang', 'rong', 'ngang', 'sau', 'cao', 'gia', 'xem', 'co', 'khong', 'san', 'pham', 'the', 'for', 'with', 'under', 'and', 'room' );
		$tokens = array_values(
			array_filter(
				explode( ' ', $q_norm ),
				static function( $t ) use ( $stop_words ) {
					return strlen( $t ) >= 3 && ! is_numeric( $t ) && ! in_array( $t, $stop_words, true );
				}
			)
		);

		$scored = array();
		foreach ( $catalog as $item ) {
			// Hard spatial clearance filter when customer specifies room limits
			if ( $c['max_width'] > 0 && $item['width_cm'] > 0 && $item['width_cm'] > $c['max_width'] + 0.5 ) {
				continue;
			}
			if ( $c['max_depth'] > 0 && $item['depth_cm'] > 0 && $item['depth_cm'] > $c['max_depth'] + 0.5 ) {
				continue;
			}
			if ( $c['max_height'] > 0 && $item['height_cm'] > 0 && $item['height_cm'] > $c['max_height'] + 0.5 ) {
				continue;
			}
			if ( $c['max_price'] > 0 && $item['price_num'] > $c['max_price'] ) {
				continue;
			}
			if ( '' !== $c['category'] && ! in_array( $c['category'], $item['categories'], true ) ) {
				continue;
			}

			$haystack = self::normalize_ascii( $item['name'] . ' ' . $item['name_en'] . ' ' . $item['sku'] . ' ' . implode( ' ', $item['categories'] ) );
			$score    = 0;

			if ( '' !== $c['category'] ) {
				$score += 30;
			}

			// Color bonus
			if ( ! empty( $c['colors'] ) ) {
				$color_matched = false;
				foreach ( $c['colors'] as $col_k ) {
					if ( false !== strpos( $haystack, $col_k )
						|| ( 'walnut' === $col_k && false !== strpos( $haystack, 'oc cho' ) )
						|| ( 'oak' === $col_k && false !== strpos( $haystack, 'soi' ) )
						|| ( 'white' === $col_k && false !== strpos( $haystack, 'trang' ) )
						|| ( 'grey' === $col_k && false !== strpos( $haystack, 'xam' ) )
					) {
						$color_matched = true;
						$score += 25;
					}
				}
				if ( ! $color_matched ) {
					continue;
				}
			}

			// Token matching
			foreach ( $tokens as $tk ) {
				if ( false !== strpos( $haystack, $tk ) ) {
					$score += 15;
				}
			}

			// Space utilization bonus: prefer pieces that fill 70%–100% of max_width cleanly
			if ( $c['max_width'] > 0 && $item['width_cm'] > 0 ) {
				$ratio = $item['width_cm'] / $c['max_width'];
				$score += (int) round( $ratio * 20 );
			}
			if ( $c['max_depth'] > 0 ) {
				$score += 10;
			}
			if ( $c['max_price'] > 0 ) {
				$score += 10;
			}

			// Do NOT return random items if user typed an unrelated question with 0 matches
			if ( 0 === $score ) {
				continue;
			}

			if ( $item['has_3d'] ) {
				$score += 3;
			}

			$item['_score'] = $score;
			$scored[]       = $item;
		}

		usort(
			$scored,
			static function( $a, $b ) {
				return $b['_score'] <=> $a['_score'];
			}
		);

		return $scored;
	}

	/**
	 * Call Google Gemini 2.5 Flash grounded strictly on matched DeSpace catalog items.
	 */
	private static function call_gemini_grounded( string $api_key, string $question, bool $is_en, array $matched, array $constraints ): ?string {
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return null;
		}

		$top_context = array_slice( $matched, 0, 5 );
		$catalog_lines = array();
		foreach ( $top_context as $idx => $p ) {
			$catalog_lines[] = sprintf(
				'%d. %s (SKU: %s) — Giá: %s — Kích thước: %s — Có 3D/AR 1:1: %s',
				$idx + 1,
				$p['name'],
				$p['sku'],
				$p['price'],
				$p['dimensions'] ?: 'Tiêu chuẩn',
				$p['has_3d'] ? 'Có' : 'Không'
			);
		}

		$sys_prompt = $is_en
			? 'You are DeSpace AI Interior Concierge for a luxury Scandinavian furniture store in Vietnam. Reply concisely (2-4 sentences) in English. ONLY recommend products from the LIVE CATALOG CONTEXT below, citing exact dimensions (cm) and price. Mention that customers can tap the 3D/AR button below to place the item at 1:1 scale in their room. Store policies: Free nationwide shipping from 5,000,000 VND, COD & Napas247 VietQR supported, 5-year warranty.'
			: 'Bạn là Chuyên gia Tư vấn Nội thất AI của DeSpace. Trả lời ngắn gọn, tinh tế (2-4 câu) bằng Tiếng Việt. CHỈ tư vấn dựa trên DANH SÁCH SẢN PHẨM THỰC TẾ bên dưới, nêu rõ kích thước (Rộng × Sâu × Cao cm) so với không gian khách hỏi và giá bán. Nhắc khách có thể bấm nút 3D/AR ngay bên dưới để đặt thử sản phẩm tỷ lệ 1:1 vào phòng. Chính sách: Miễn phí giao lắp từ 5.000.000 ₫, hỗ trợ COD & VietQR tự động, bảo hành 5 năm.';

		$context_block = empty( $catalog_lines )
			? 'LIVE CATALOG CONTEXT: Không có sản phẩm nào khớp giới hạn kích thước/ngân sách này.'
			: "LIVE CATALOG CONTEXT:\n" . implode( "\n", $catalog_lines );

		$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

		$body = array(
			'systemInstruction' => array(
				'parts' => array( array( 'text' => $sys_prompt ) ),
			),
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $context_block . "\n\nCâu hỏi khách hàng: " . $question ) ),
				),
			),
			'generationConfig'  => array(
				'temperature'     => 0.35,
				'maxOutputTokens' => 320,
			),
		);

		$resp = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $resp ), true );
		$text    = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
		return is_string( $text ) && '' !== trim( $text ) ? trim( wp_strip_all_tags( $text ) ) : null;
	}

	private static function get_gemini_api_key(): string {
		$env_key = getenv( 'GEMINI_API_KEY' );
		if ( is_string( $env_key ) && '' !== trim( $env_key ) ) {
			return trim( $env_key );
		}
		if ( defined( 'DESPACE_GEMINI_API_KEY' ) && '' !== trim( (string) DESPACE_GEMINI_API_KEY ) ) {
			return trim( (string) DESPACE_GEMINI_API_KEY );
		}
		if ( function_exists( 'get_option' ) ) {
			return trim( (string) get_option( 'despace_gemini_api_key', '' ) );
		}
		return '';
	}

	private static function normalize_ascii( string $str ): string {
		$s = function_exists( 'remove_accents' ) ? remove_accents( $str ) : $str;
		$s = str_replace( array( 'đ', 'Đ' ), 'd', $s );
		$s = strtolower( $s );
		$s = preg_replace( '/[^a-z0-9\.\,\s]/', ' ', $s );
		$s = preg_replace( '/\s+/', ' ', (string) $s );
		return trim( (string) $s );
	}
}
