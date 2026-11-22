<?php
/**
 * DeSpace Canonical Informational & Policy Pages Data (Approved Source of Truth)
 *
 * Stored directly in theme repository in clean UTF-8.
 * Automatically restored after database seed synchronization.
 *
 * @package DeSpace_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
        11 => array(
            'slug'    => 've-despace',
            'title'   => 'Về DeSpace',
            'status'  => 'publish',
            'content' => <<<'HTML'
<!-- wp:html -->
<div class="f-wrap ds-about-page-wrap">
    <!-- Academic Simulation Disclaimer -->
    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;padding:12px 18px;margin-bottom:28px;font-size:13px;color:#92400E;line-height:1.6;">
        <strong>Thông báo đồ án DeSpace:</strong> Website này được xây dựng nhằm mục đích thực hành và báo cáo đồ án môn học. Các thông tin về lịch sử thành lập, chứng chỉ vật liệu và cam kết bảo hành mang tính chất mô phỏng kịch bản, không phải thực thể thương mại độc lập.
    </div>
    <div class="f-about-grid">
        <div>
            <span class="f-kicker" style="color:#b8845a;font-weight:700;letter-spacing:2.5px;text-transform:uppercase;font-size:12px;">Kể từ 2016</span>
            <h2>Nội thất dành cho nhịp sống mỗi ngày</h2>
            <p>DeSpace khởi nguồn từ một xưởng thiết kế nội thất tại Hà Nội với một triết lý giản dị: kiến tạo những sản phẩm nội thất cao cấp chỉn chu, kết hợp công nghệ trực quan hóa không gian thực tế tăng cường 3D/AR để khách hàng hình dung trọn vẹn nhất từng món đồ trong ngôi nhà của mình.</p>
            <p>Chúng tôi tuyển chọn các dòng gỗ sồi, gỗ óc chó tự nhiên đạt chứng nhận FSC, vải bọc đạt chuẩn OEKO-TEX Standard 100 và phụ kiện bản lề, ray trượt giảm chấn Blum, Hafele tinh xảo. Mỗi thiết kế đều được thử nghiệm và hoàn thiện tỉ mỉ trước khi đến tay bạn.</p>
            <p><strong>DeSpace Sáng lập &amp; Giám đốc thiết kế:</strong> Tư vấn trực tiếp không gian sống &amp; hoàn thiện nội thất cao cấp theo phong cách Scandinavian, Quiet Luxury &amp; Muji Serenity.</p>
            <div style="display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;">
                <a class="f-btn f-btn--primary" href="/shop/">Khám phá bộ sưu tập</a>
                <a class="f-btn f-btn--secondary" href="/lien-he/">Đặt lịch tư vấn Showroom</a>
            </div>
        </div>
        <div>
            <img src="/wp-content/uploads/hero.jpg" alt="Studio thiết kế và xưởng chế tác nội thất DeSpace" width="800" height="600" />
        </div>
    </div>

    <!-- Technology & Craftsmanship Pillar -->
    <div class="ds-about-pillars" style="margin: 60px 0 30px;">
        <div style="text-align:center;max-width:700px;margin:0 auto 36px;">
            <span class="f-kicker" style="color:#b8845a;font-weight:700;letter-spacing:2px;text-transform:uppercase;font-size:11px;">GIÁ TRỊ CỐT LÕI</span>
            <h2 style="font-family:var(--f-font-editorial, serif);font-size:clamp(28px,3.5vw,38px);margin:8px 0 14px;">Hài Hòa Giữa Thủ Công Tinh Tuyển &amp; Công Nghệ Tiên Phong</h2>
            <p style="color:#69645D;font-size:14px;line-height:1.7;">Không chỉ là những món đồ nội thất đơn thuần, DeSpace tạo dựng những điểm chạm cảm xúc cho ngôi nhà, nơi bạn luôn muốn trở về sau một ngày dài.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:24px;">
            <div style="background:#FAF7F2;padding:28px 24px;border-radius:8px;border:1px solid #E7E0D7;">
                <span style="font-size:28px;display:block;margin-bottom:12px;" aria-hidden="true">🌱</span>
                <h3 style="font-size:18px;margin:0 0 10px;font-family:var(--f-font-editorial, serif);color:#1C1917;">Chất Liệu Bền Vững Chuẩn FSC</h3>
                <p style="font-size:13px;color:#69645D;line-height:1.7;margin:0;">Toàn bộ gỗ tự nhiên và cốt ván cao cấp đều có chứng chỉ bảo vệ rừng bền vững FSC. Bề mặt phủ sơn lau thực vật an toàn, không chứa hóa chất độc hại, thân thiện với trẻ nhỏ.</p>
            </div>
            <div style="background:#FAF7F2;padding:28px 24px;border-radius:8px;border:1px solid #E7E0D7;">
                <span style="font-size:28px;display:block;margin-bottom:12px;" aria-hidden="true">📐</span>
                <h3 style="font-size:18px;margin:0 0 10px;font-family:var(--f-font-editorial, serif);color:#1C1917;">Trực Quan Hóa 3D &amp; AR 1:1</h3>
                <p style="font-size:13px;color:#69645D;line-height:1.7;margin:0;">Ướm thử sản phẩm trực tiếp vào căn phòng của bạn qua camera thực tế tăng cường AR trên điện thoại trước khi đưa ra quyết định mua hàng. Tỷ lệ chính xác từng centimet.</p>
            </div>
            <div style="background:#FAF7F2;padding:28px 24px;border-radius:8px;border:1px solid #E7E0D7;">
                <span style="font-size:28px;display:block;margin-bottom:12px;" aria-hidden="true">🛠️</span>
                <h3 style="font-size:18px;margin:0 0 10px;font-family:var(--f-font-editorial, serif);color:#1C1917;">May Đo Theo Yêu Cầu Riêng</h3>
                <p style="font-size:13px;color:#69645D;line-height:1.7;margin:0;">Xưởng sản xuất riêng của DeSpace sẵn sàng tinh chỉnh kích thước, chiều cao, màu sơn hoặc kết cấu ngăn kéo theo diện tích mặt bằng căn hộ thực tế của gia đình bạn.</p>
            </div>
        </div>
    </div>
</div>
<!-- /wp:html -->
HTML
        ),
        12 => array(
            'slug'    => 'lien-he',
            'title'   => 'Liên hệ',
            'status'  => 'publish',
            'content' => <<<'HTML'
<!-- wp:html -->
<div class="f-wrap f-contact-luxury-page">
    <!-- 1. Showroom & Founder Hero Card -->
    <div class="ds-contact-hero-card">
        <div class="ds-contact-hero-badge">DeSpace ARCHITECTURAL SANCTUARY</div>
        <h2 class="ds-contact-hero-title">Không Gian Sáng Tạo &amp; Showroom Trải Nghiệm 3D/AR</h2>
        <p class="ds-contact-hero-subtitle">Nơi khởi nguồn cho những không gian sống đậm chất Scandinavian, Muji Serenity &amp; Quiet Luxury. Chúng tôi sẵn sàng đồng hành cùng bạn từ bản vẽ ý tưởng đến hiện thực hóa từng chi tiết nội thất tinh xảo.</p>
        
        <div class="ds-contact-founder-strip">
            <div class="ds-founder-avatar" aria-hidden="true">DS</div>
            <div class="ds-founder-info">
                <strong>DeSpace Sáng lập &amp; Giám đốc thiết kế</strong>
                <span>Tư vấn trực tiếp không gian sống &amp; hoàn thiện nội thất cao cấp</span>
            </div>
        </div>
        <div style="background:rgba(255,251,235,0.15);border:1px solid rgba(253,230,138,0.3);border-radius:6px;padding:8px 14px;margin-top:14px;font-size:12px;color:#FAF7F2;line-height:1.5;">
            * <em>Thông tin địa chỉ showroom, hotline và nhân sự đại diện dưới đây mang tính chất giả lập phục vụ kịch bản đồ án môn học DeSpace.</em>
        </div>
    </div>

    <!-- 2. Grid: Showroom Details & Booking Form -->
    <div class="f-contact-grid ds-contact-main-grid">
        <!-- Col Left: Showroom Directory & Facilities -->
        <div class="f-contact-info ds-showroom-info-box">
            <h3 class="ds-box-heading">Thông Tin Showroom &amp; Đón Tiếp</h3>
            <ul class="ds-info-list">
                <li>
                    <span class="ds-info-icon" aria-hidden="true">📍</span>
                    <div>
                        <strong>Địa chỉ Showroom (Mô phỏng đồ án):</strong>
                        <p>Tòa nhà DeSpace, Số 123 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội (Mô phỏng đồ án)</p>
                        <small class="ds-sub-tip">Không gian trưng bày trải nghiệm nội thất &amp; tư vấn thiết kế 3D/AR (Dữ liệu đồ án thực nghiệm).</small>
                    </div>
                </li>
                <li>
                    <span class="ds-info-icon" aria-hidden="true">📞</span>
                    <div>
                        <strong>Hotline Tư Vấn (Mô phỏng đồ án):</strong>
                        <p><span class="ds-link-highlight">(024) 0000 0000 (Mô phỏng đồ án)</span></p>
                        <small class="ds-sub-tip">Hỗ trợ tư vấn kích thước và phối cảnh 3D mô phỏng kịch bản đồ án.</small>
                    </div>
                </li>
                <li>
                    <span class="ds-info-icon" aria-hidden="true">✉️</span>
                    <div>
                        <strong>Hòm thư Tiếp Nhận Bản Vẽ:</strong>
                        <p><span class="ds-link-highlight">contact@despace.test (Mô phỏng đồ án)</span></p>
                        <small class="ds-sub-tip">Tiếp nhận mặt bằng căn hộ mẫu, bản vẽ CAD mô phỏng trong kịch bản đồ án.</small>
                    </div>
                </li>
                <li>
                    <span class="ds-info-icon" aria-hidden="true">⏰</span>
                    <div>
                        <strong>Giờ Mở Cửa Trải Nghiệm:</strong>
                        <p>08:30 – 21:30 (Thứ Hai – Chủ Nhật)</p>
                        <small class="ds-sub-tip">Mở cửa tất cả các ngày trong tuần, kể cả ngày lễ.</small>
                    </div>
                </li>
                <li>
                    <span class="ds-info-icon" aria-hidden="true">🅿️</span>
                    <div>
                        <strong>Tiện Ích Đón Tiếp:</strong>
                        <p>Bãi đỗ xe ô tô &amp; xe máy an ninh rộng rãi miễn phí, đồ uống tiếp đón cao cấp, iPad Pro trải nghiệm AR 3D thực tế ảo.</p>
                    </div>
                </li>
            </ul>

            <div class="ds-dept-directory">
                <h4>Danh Bạ Bộ Phận Chuyên Trách (Mô phỏng)</h4>
                <div class="ds-dept-item">
                    <strong>Phòng Thiết Kế &amp; Kiến Trúc:</strong>
                    <span>Tổng đài mô phỏng: (024) 0000 0000 (Nhánh 1) | kts@despace.test</span>
                </div>
                <div class="ds-dept-item">
                    <strong>Phòng Dự Án B2B &amp; Biệt Thự:</strong>
                    <span>Tổng đài mô phỏng: (024) 0000 0000 (Nhánh 2) | b2b@despace.test</span>
                </div>
                <div class="ds-dept-item">
                    <strong>Phòng Dịch Vụ Khách Hàng &amp; Bảo Hành:</strong>
                    <span>Hỗ trợ bảo hành kết cấu (Mô phỏng đồ án) | support@despace.test</span>
                </div>
            </div>
        </div>

        <!-- Col Right: Interactive Consultation Form -->
        <div class="f-contact-form ds-booking-form-box">
            <h3 class="ds-box-heading">Đặt Lịch Tư Vấn Trực Tiếp</h3>
            <p class="ds-form-intro">Đặt hẹn để được Giám đốc thiết kế DeSpace đón tiếp và tư vấn riêng giải pháp không gian cho ngôi nhà của bạn.</p>

            [despace_contact_form]
        </div>
    </div>

    <!-- 3. Our 4-Step Bespoke Consultation Process -->
    <div class="ds-process-section">
        <div class="ds-section-head-center">
            <span class="f-kicker">QUY TRÌNH CHUYÊN NGHIỆP</span>
            <h2>4 Bước Đồng Hành Cùng Gia Chủ</h2>
            <p>Từ những phác thảo ban đầu đến khi từng sản phẩm hoàn thiện được đặt trọn vẹn trong ngôi nhà của bạn.</p>
        </div>
        <div class="ds-process-grid">
            <div class="ds-process-card">
                <span class="ds-step-badge">01</span>
                <h3>Lắng Nghe &amp; Thấu Hiểu</h3>
                <p>Trao đổi về phong cách sống, thói quen sinh hoạt và nhu cầu lưu trữ để định hình giải pháp không gian hài hòa.</p>
            </div>
            <div class="ds-process-card">
                <span class="ds-step-badge">02</span>
                <h3>Khảo Sát &amp; Demo 3D/AR</h3>
                <p>Đo đạc hiện trạng, dựng mô hình 3D tỷ lệ 1:1 và cho bạn trải nghiệm sản phẩm đặt trong phòng qua camera AR trước khi quyết định.</p>
            </div>
            <div class="ds-process-card">
                <span class="ds-step-badge">03</span>
                <h3>Duyệt Mẫu &amp; Báo Giá</h3>
                <p>Trực tiếp kiểm tra mẫu gỗ sồi, óc chó chuẩn FSC, vải OEKO-TEX và bảng báo giá chi tiết, minh bạch không phát sinh.</p>
            </div>
            <div class="ds-process-card">
                <span class="ds-step-badge">04</span>
                <h3>Chế Tác &amp; Bàn Giao</h3>
                <p>Gia công tỉ mỉ tại xưởng DeSpace, vận chuyển bọc lót chống sốc 3 lớp an toàn và kỹ thuật viên lắp đặt hoàn thiện sạch sẽ.</p>
            </div>
        </div>
    </div>

    <!-- 4. Interactive Google Maps & Route Guide -->
    <div class="ds-map-section">
        <div class="ds-map-header">
            <div>
                <h3>Vị Trí Showroom DeSpace Trên Bản Đồ (Mô phỏng đồ án)</h3>
                <p>Tòa nhà DeSpace, Số 123 Đường Nguyễn Trãi, Thanh Xuân, Hà Nội (Mô phỏng đồ án)</p>
            </div>
            <a href="https://maps.google.com/?q=Hà+Nội" target="_blank" rel="noopener noreferrer" class="f-btn f-btn--secondary ds-open-maps-btn">
                <span>Mở Trên Google Maps ↗</span>
            </a>
        </div>
    </div>

    <!-- 5. Showroom FAQ -->
    <div class="ds-faq-section">
        <h3 class="ds-faq-title" style="font-family:var(--f-font-editorial, serif);font-size:24px;margin-bottom:20px;">Câu Hỏi Thường Gặp Khi Ghé Thăm Showroom</h3>
        <div class="ds-faq-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:20px;">
            <div class="ds-faq-item" style="background:#FAF7F2;padding:20px;border-radius:8px;border:1px solid #E7E0D7;">
                <h4 style="font-size:15px;margin:0 0 8px;color:#1C1917;">Có cần đặt lịch hẹn trước khi đến Showroom không?</h4>
                <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">Bạn có thể ghé thăm bất cứ lúc nào trong khung giờ 08:30 – 21:30. Tuy nhiên, chúng tôi khuyến khích đặt lịch hẹn trước để Giám đốc thiết kế bố trí thời gian tư vấn 1-1 chuyên sâu và chuẩn bị sẵn mô hình 3D phù hợp với mặt bằng nhà bạn.</p>
            </div>
            <div class="ds-faq-item" style="background:#FAF7F2;padding:20px;border-radius:8px;border:1px solid #E7E0D7;">
                <h4 style="font-size:15px;margin:0 0 8px;color:#1C1917;">Showroom có sẵn mô hình 3D và mẫu vật liệu thực tế không?</h4>
                <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">Có đầy đủ. Showroom trưng bày các dòng sản phẩm tiêu biểu cùng hệ thống mẫu gỗ sồi, gỗ óc chó tự nhiên, bề mặt hoàn thiện, phụ kiện bản lề Blum/Hafele và thiết bị iPad Pro để quý khách trải nghiệm trực tiếp tính năng chiếu AR vào phòng.</p>
            </div>
            <div class="ds-faq-item" style="background:#FAF7F2;padding:20px;border-radius:8px;border:1px solid #E7E0D7;">
                <h4 style="font-size:15px;margin:0 0 8px;color:#1C1917;">DeSpace có nhận chế tác kích thước theo yêu cầu không?</h4>
                <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">Hoàn toàn có. Với xưởng chế tác riêng, DeSpace sẵn sàng đo đạc và điều chỉnh tỷ lệ, số lượng ngăn kéo, khoang chứa đồ để vừa vặn tuyệt đối với diện tích và hốc tường của từng căn hộ.</p>
            </div>
            <div class="ds-faq-item" style="background:#FAF7F2;padding:20px;border-radius:8px;border:1px solid #E7E0D7;">
                <h4 style="font-size:15px;margin:0 0 8px;color:#1C1917;">Chính sách giao hàng và lắp đặt được thực hiện thế nào?</h4>
                <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">Chúng tôi miễn phí vận chuyển và lắp đặt trong nội thành Hà Nội cho các đơn hàng từ 10.000.000đ. Đội ngũ kỹ thuật viên tay nghề cao sẽ bàn giao và hướng dẫn bảo dưỡng bề mặt cẩn thận trước khi rời đi.</p>
            </div>
        </div>
    </div>
</div>
<!-- /wp:html -->
HTML
        ),
        14 => array(
            'slug'    => 'cau-hoi-thuong-gap',
            'title'   => 'Câu hỏi thường gặp',
            'status'  => 'publish',
            'content' => <<<'HTML'
<!-- wp:html -->
<div class="f-wrap ds-faq-page-wrap" style="max-width:960px;margin:0 auto;padding-top:20px;padding-bottom:60px;">
    <div style="text-align:center;margin-bottom:40px;">
        <span class="f-kicker" style="color:#b8845a;font-weight:700;letter-spacing:2px;text-transform:uppercase;font-size:11px;">GIẢI ĐÁP THẮC MẮC</span>
        <h2 style="font-family:var(--f-font-editorial, serif);font-size:clamp(28px,3.5vw,38px);margin:8px 0 14px;">Câu Hỏi Thường Gặp Về Sản Phẩm &amp; Mua Hàng</h2>
        <p style="color:#69645D;font-size:14px;line-height:1.7;max-width:640px;margin:0 auto;">Tổng hợp các thắc mắc phổ biến nhất của khách hàng về quy cách chế tác gỗ, trải nghiệm 3D/AR, quy trình giao hàng và chính sách bảo hành tại DeSpace.</p>
    </div>

    <!-- Academic Simulation Disclaimer -->
    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;padding:12px 18px;margin-bottom:32px;font-size:13px;color:#92400E;line-height:1.6;">
        <strong>Lưu ý kịch bản đồ án:</strong> Các giải đáp liên quan đến chính sách bảo hành kết cấu, đổi trả và vận chuyển là nội dung mô phỏng phục vụ xây dựng quy trình thương mại điện tử cho đồ án DeSpace, chưa áp dụng thực tế.
    </div>

    <!-- Group 1: Sản phẩm & Chất liệu -->
    <div class="ds-faq-group" style="margin-bottom:36px;">
        <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;border-bottom:2px solid #E7E0D7;padding-bottom:10px;margin-bottom:18px;">1. Sản Phẩm &amp; Chất Liệu Chế Tác</h3>
        
        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;" open>
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Gỗ tự nhiên của DeSpace có bị cong vênh hoặc mối mọt trong khí hậu Việt Nam không?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">Toàn bộ gỗ sồi và óc chó tự nhiên tại DeSpace đều trải qua quy trình tẩm sấy tiêu chuẩn đạt độ ẩm 10 – 12% trước khi đưa vào xưởng chế tác. Bề mặt gỗ được hoàn thiện bằng dầu lau gốc thực vật hoặc sơn phủ PU mờ cao cấp, giúp gỗ "thở" tự nhiên nhưng kháng ẩm vượt trội, hạn chế tối đa nguy cơ co ngót hay cong vênh.</p>
        </details>

        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>DeSpace có nhận may đo kích thước hoặc thay đổi màu sắc theo yêu cầu không?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">Có. Chúng tôi sở hữu xưởng sản xuất trực tiếp nên hoàn toàn có thể điều chỉnh chiều dài, chiều rộng, chiều cao hoặc tùy biến màu sơn (sồi tự nhiên Modern Oak, nâu đậm Nâu Wenge...) để phù hợp tuyệt đối với không gian căn hộ của bạn.</p>
        </details>

        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Phụ kiện ray trượt và bản lề sử dụng thương hiệu nào?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">DeSpace sử dụng phụ kiện kim khí giảm chấn chính hãng từ Blum (Áo) và Hafele (Đức). Các phụ kiện này được kiểm định vận hành êm ái với độ bền vượt trên 50.000 chu kỳ đóng mở.</p>
        </details>
    </div>

    <!-- Group 2: Trực quan 3D & AR -->
    <div class="ds-faq-group" style="margin-bottom:36px;">
        <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;border-bottom:2px solid #E7E0D7;padding-bottom:10px;margin-bottom:18px;">2. Công Nghệ 3D &amp; Thực Tế Ảo Tăng Cường (AR)</h3>
        
        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Tính năng xem phòng 3D/AR hoạt động như thế nào? Có cần cài app không?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">Quý khách <strong>không cần cài đặt thêm bất kỳ ứng dụng nào</strong>. Khi bấm nút "Xem 3D &amp; Đặt trong phòng (AR)" trên trang chi tiết sản phẩm bằng điện thoại di động (iPhone/iPad hoặc Android), trình duyệt sẽ tự động kích hoạt camera để quét mặt sàn và hiển thị mô hình nội thất tỷ lệ 1:1 trong phòng. Nếu đang dùng máy tính, bạn có thể quét mã QR hiển thị trên màn hình bằng điện thoại để xem trực tiếp.</p>
        </details>

        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Kích thước mô hình AR có chính xác so với sản phẩm thực tế không?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">Mô hình 3D AR của DeSpace được dựng chính xác theo thông số kỹ thuật từng milimet của sản phẩm thực tế. Khi đặt trong phòng, mô hình hiển thị đúng tỷ lệ không gian, giúp bạn kiểm tra lối đi lại và sự hài hòa với các món đồ nội thất xung quanh.</p>
        </details>
    </div>

    <!-- Group 3: Đặt hàng & Thanh toán -->
    <div class="ds-faq-group" style="margin-bottom:36px;">
        <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;border-bottom:2px solid #E7E0D7;padding-bottom:10px;margin-bottom:18px;">3. Quy Trình Đặt Hàng &amp; Phương Thức Thanh Toán</h3>
        
        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>DeSpace hỗ trợ những phương thức thanh toán nào?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">Quý khách có thể lựa chọn các hình thức thanh toán linh hoạt: Thanh toán khi nhận hàng (COD), Chuyển khoản ngân hàng trực tiếp, hoặc Thẻ thanh toán quốc tế (Visa, Mastercard, JCB) thông qua cổng bảo mật.</p>
        </details>

        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Tôi có được áp dụng mã giảm giá khi đặt hàng không?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">Có. Quý khách có thể nhập mã ưu đãi <strong>DESPACE10</strong> (giảm 10%) hoặc <strong>DESPACE500K</strong> (giảm 500.000 ₫) tại trang giỏ hàng hoặc thanh toán.</p>
        </details>
    </div>

    <!-- Group 4: Vận chuyển & Bảo hành -->
    <div class="ds-faq-group" style="margin-bottom:36px;">
        <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;border-bottom:2px solid #E7E0D7;padding-bottom:10px;margin-bottom:18px;">4. Giao Hàng &amp; Chính Sách Bảo Hành</h3>
        
        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Thời gian giao hàng mất bao lâu? Có nhân viên lắp đặt tại nhà không?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">Đối với sản phẩm có sẵn tại showroom hoặc kho, thời gian giao trong nội thành Hà Nội là 1 – 3 ngày làm việc; các tỉnh thành khác là 3 – 7 ngày làm việc. Đội ngũ kỹ thuật viên của DeSpace sẽ trực tiếp vận chuyển, đưa sản phẩm vào tận phòng, căn chỉnh hoàn thiện và thu dọn màng bọc sạch sẽ.</p>
        </details>

        <details class="ds-faq-accordion" style="background:#FAF7F2;border:1px solid #E7E0D7;border-radius:6px;padding:16px 20px;margin-bottom:12px;">
            <summary style="font-weight:600;font-size:15px;color:#1C1917;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;">
                <span>Thời hạn và điều kiện bảo hành sản phẩm như thế nào?</span>
                <span aria-hidden="true" style="color:#9A6745;font-size:18px;">▾</span>
            </summary>
            <p style="font-size:13.5px;color:#69645D;line-height:1.75;margin:12px 0 0;">DeSpace áp dụng chính sách hỗ trợ bảo hành kết cấu khung gỗ và đổi trả đối với lỗi kỹ thuật từ nhà sản xuất theo khung quy trình mô phỏng đồ án (thời hạn cụ thể sẽ được công bố chính thức khi triển khai thực tế). Quý khách có thể liên hệ biểu mẫu hỗ trợ kèm mã đơn hàng để được hướng dẫn.</p>
        </details>
    </div>

    <!-- Quick Help Card -->
    <div style="background:#1C1917;color:#FAF7F2;padding:32px 28px;border-radius:8px;text-align:center;margin-top:40px;">
        <h3 style="font-family:var(--f-font-editorial, serif);font-size:24px;color:#FAF7F2;margin:0 0 10px;">Bạn Cần Tư Vấn Riêng Cho Không Gian Của Mình?</h3>
        <p style="color:#D6D3D1;font-size:14px;max-width:550px;margin:0 auto 20px;line-height:1.6;">Đội ngũ kiến trúc sư và Giám đốc thiết kế DeSpace luôn sẵn sàng lắng nghe và đưa ra giải pháp phù hợp nhất với căn nhà của bạn (Dữ liệu đồ án thực nghiệm).</p>
        <div style="display:flex;justify-content:center;gap:14px;flex-wrap:wrap;">
            <a href="/lien-he/" class="f-btn f-btn--primary" style="background:#C5A880;color:#1C1917;">Đặt Lịch Tư Vấn 1-1</a>
            <a href="/lien-he/" class="f-btn f-btn--secondary" style="border-color:rgba(255,255,255,0.2);color:#FAF7F2;">Kênh Hỗ Trợ Mô Phỏng</a>
        </div>
    </div>
</div>
<!-- /wp:html -->
HTML
        ),
        13 => array(
            'slug'    => 'chinh-sach-giao-hang',
            'title'   => 'Chính sách giao hàng & lắp đặt',
            'status'  => 'publish',
            'content' => <<<'HTML'
<!-- wp:html -->
<div class="f-wrap ds-policy-page-wrap" style="max-width:960px;margin:0 auto;padding-top:20px;padding-bottom:60px;">
    <div style="margin-bottom:32px;">
        <span class="f-kicker" style="color:#b8845a;font-weight:700;letter-spacing:2px;text-transform:uppercase;font-size:11px;">DỊCH VỤ VẬN CHUYỂN &amp; LẮP ĐẶT</span>
        <h2 style="font-family:var(--f-font-editorial, serif);font-size:clamp(28px,3.5vw,38px);margin:8px 0 14px;">Chính Sách Giao Hàng &amp; Bài Trí Tận Nơi</h2>
        <p style="color:#69645D;font-size:14px;line-height:1.7;">Tại DeSpace, trải nghiệm giao nhận không đơn thuần là vận chuyển hàng hóa, mà là sự đồng hành chỉn chu đưa từng món đồ tinh tuyển an tọa chuẩn xác trong không gian sống của bạn.</p>
    </div>

    <!-- Academic Simulation Disclaimer -->
    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;padding:14px 18px;margin-bottom:28px;font-size:13px;color:#92400E;line-height:1.6;">
        <strong>Lưu ý mô phỏng đồ án:</strong> Chính sách vận chuyển và biểu phí giao hàng dưới đây được xây dựng dưới dạng kịch bản mẫu phục vụ đồ án DeSpace. Biểu phí thực tế và thỏa thuận đơn vị vận chuyển cồng kềnh đang trong giai đoạn khảo sát, chưa phải cam kết thương mại chính thức.
    </div>

    <div style="display:grid;gap:28px;">
        <!-- Section 1 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">1. Phạm Vi Vận Chuyển Toàn Quốc</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:12px;">DeSpace cung cấp dịch vụ giao hàng trên toàn quốc với 2 phương thức điều phối chuyên nghiệp:</p>
            <ul style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0;">
                <li><strong>Khu vực Hà Nội (Nội thành &amp; Lân cận):</strong> Đội ngũ kỹ thuật viên chuyên trách của DeSpace trực tiếp vận chuyển bằng xe tải chuyên dụng và lắp đặt bài trí hoàn thiện.</li>
                <li><strong>Các tỉnh thành khác trên toàn quốc:</strong> Sản phẩm được đóng kiện gỗ gia cố, bọc 3 lớp chống sốc chuyên dụng và vận chuyển thông qua đối tác logistics hàng cồng kềnh uy tín, đảm bảo an toàn tuyệt đối suốt hành trình.</li>
            </ul>
        </section>

        <!-- Section 2 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">2. Thời Gian Giao Nhận Ước Tính</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;margin-top:14px;">
                <div style="background:#fff;padding:18px;border-radius:6px;border:1px solid #E7E0D7;">
                    <strong style="color:#1C1917;font-size:15px;display:block;margin-bottom:6px;">Sản phẩm có sẵn tại Kho / Showroom</strong>
                    <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">• Nội thành Hà Nội: Giao trong vòng <strong>1 – 3 ngày làm việc</strong>.<br>• Các tỉnh thành khác: Giao trong vòng <strong>3 – 7 ngày làm việc</strong>.</p>
                </div>
                <div style="background:#fff;padding:18px;border-radius:6px;border:1px solid #E7E0D7;">
                    <strong style="color:#1C1917;font-size:15px;display:block;margin-bottom:6px;">Sản phẩm Chế tác theo Yêu cầu</strong>
                    <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">Thời gian gia công và hoàn thiện bề mặt từ <strong>7 – 15 ngày làm việc</strong> (tiến độ chi tiết sẽ được ghi rõ trên phiếu xác nhận đơn hàng).</p>
                </div>
            </div>
        </section>

        <!-- Section 3 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">3. Biểu Phí Vận Chuyển &amp; Hỗ Trợ Đơn Hàng (Kịch bản mô phỏng)</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:14px;">Chính sách hỗ trợ vận chuyển mô phỏng của DeSpace được dự kiến như sau:</p>
            <ul style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0 0 16px;">
                <li><strong>Đơn hàng từ 10.000.000 VNĐ trở lên (hoặc từ $500) (Minh họa):</strong> Miễn phí chi phí vận chuyển và công lắp đặt hoàn thiện trong nội thành Hà Nội.</li>
                <li><strong>Đơn hàng dưới 10.000.000 VNĐ hoặc đi các tỉnh khác (Minh họa):</strong> Áp dụng mức phí hỗ trợ dựa trên khoảng cách địa lý và biểu cước thực tế của đối tác vận tải cồng kềnh.</li>
            </ul>

            <!-- Course Project Simulation Note -->
            <div style="background:#FAF7F2;border-left:4px solid #b8845a;padding:14px 18px;border-radius:4px;font-size:13px;color:#69645D;line-height:1.6;">
                <strong>Lưu ý mô phỏng đồ án:</strong> Biểu phí vận chuyển chi tiết theo từng km ngoại thành xa và phụ phí bốc xếp cho các trường hợp đặc biệt được xây dựng dưới dạng kịch bản mô phỏng cho đồ án học tập. Trong kịch bản trải nghiệm, khách hàng liên hệ qua biểu mẫu liên hệ hoặc kênh hỗ trợ mô phỏng để được tư vấn mức phí minh họa phù hợp.
            </div>
        </section>

        <!-- Section 4 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">4. Quy Trình Đồng Kiểm (White-Glove Delivery)</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:12px;">Đảm bảo sự an tâm trọn vẹn cho khách hàng khi nhận sản phẩm:</p>
            <ol style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0;">
                <li>Kỹ thuật viên liên hệ trước giờ giao tối thiểu 2 – 4 tiếng để quý khách sắp xếp thời gian đón nhận.</li>
                <li>Hỗ trợ đưa sản phẩm vào đúng vị trí chỉ định trong nhà, cân chỉnh độ thăng bằng của chân đế, kiểm tra độ êm của ngăn kéo và bản lề cánh tủ.</li>
                <li>Quý khách được <strong>đồng kiểm trực tiếp ngoại quan bề mặt gỗ, màu sơn và kết cấu</strong> trước khi ký biên bản bàn giao.</li>
                <li>Đội ngũ giao hàng có trách nhiệm thu dọn toàn bộ bao bì xốp, màng bọc nilong và giữ gìn sàn nhà sạch sẽ trước khi rời đi.</li>
            </ol>
        </section>
    </div>
</div>
<!-- /wp:html -->
HTML
        ),
        9 => array(
            'slug'    => 'chinh-sach-doi-tra-bao-hanh',
            'title'   => 'Chính sách đổi trả & bảo hành',
            'status'  => 'publish',
            'content' => <<<'HTML'
<!-- wp:html -->
<div class="f-wrap ds-policy-page-wrap" style="max-width:960px;margin:0 auto;padding-top:20px;padding-bottom:60px;">
    <div style="margin-bottom:32px;">
        <span class="f-kicker" style="color:#b8845a;font-weight:700;letter-spacing:2px;text-transform:uppercase;font-size:11px;">CAM KẾT CHẤT LƯỢNG VƯỢT TRỘI</span>
        <h2 style="font-family:var(--f-font-editorial, serif);font-size:clamp(28px,3.5vw,38px);margin:8px 0 14px;">Chính Sách Đổi Trả &amp; Bảo Hành (Mô Phỏng Đồ Án)</h2>
        <p style="color:#69645D;font-size:14px;line-height:1.7;">Mỗi sản phẩm nội thất DeSpace được thiết kế để đồng hành bền bỉ cùng tổ ấm của bạn. Dưới đây là khung quy trình hỗ trợ đổi trả và bảo hành sản phẩm phục vụ kịch bản đồ án.</p>
    </div>

    <!-- Academic Simulation Disclaimer -->
    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;padding:14px 18px;margin-bottom:28px;font-size:13px;color:#92400E;line-height:1.6;">
        <strong>Lưu ý mô phỏng đồ án:</strong> Toàn bộ phạm vi bảo hành kết cấu, phụ kiện và quy trình đổi trả dưới đây được biên soạn nhằm minh họa cho kịch bản đồ án môn học DeSpace (không ấn định thời hạn thương mại cố định). Đây là nội dung giả lập quy trình nghiệp vụ và chưa phải cam kết pháp lý thương mại đã được doanh nghiệp ban hành.
    </div>

    <div style="display:grid;gap:28px;">
        <!-- Section 1: Returns -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">1. Chính Sách Đổi Mới Đối Với Lỗi Kỹ Thuật</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:12px;">Khách hàng có thể gửi yêu cầu đổi sản phẩm tương đương sau khi ký biên bản bàn giao nếu sản phẩm gặp các trường hợp sau (mô phỏng đồ án):</p>
            <ul style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0 0 14px;">
                <li>Sản phẩm phát sinh lỗi kỹ thuật do quá trình chế tác hoặc xử lý vật liệu của nhà sản xuất (nứt tách mộng gỗ, cong vênh vượt tiêu chuẩn, lỗi liên kết kết cấu).</li>
                <li>Sản phẩm giao không đúng quy cách, màu sắc hoặc mã hiệu so với đơn đặt hàng đã xác nhận.</li>
            </ul>
            <p style="font-size:13.5px;color:#69645D;line-height:1.6;margin:0;"><strong>Điều kiện tiếp nhận:</strong> Sản phẩm còn nguyên tem mác DeSpace, chưa bị can thiệp sửa chữa tự ý bởi bên thứ ba và có thông tin đơn hàng trên hệ thống.</p>
        </section>

        <!-- Section 2: Structural Warranty -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">2. Phạm Vi Bảo Hành Kết Cấu &amp; Phụ Kiện (Mô Phỏng)</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;margin-top:14px;margin-bottom:16px;">
                <div style="background:#fff;padding:18px;border-radius:6px;border:1px solid #E7E0D7;">
                    <span style="display:inline-block;background:#C5A880;color:#1C1917;font-size:11px;font-weight:700;padding:2px 8px;border-radius:3px;margin-bottom:8px;">BẢO HÀNH KẾT CẤU (MÔ PHỎNG)</span>
                    <strong style="color:#1C1917;font-size:15px;display:block;margin-bottom:6px;">Kết Cấu Khung Gỗ Chịu Lực</strong>
                    <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">Hỗ trợ bảo hành độ bền mối ghép mộng, độ ổn định của khung gỗ và khả năng chịu lực của các tầng kệ chính theo quy chuẩn đơn hàng.</p>
                </div>
                <div style="background:#fff;padding:18px;border-radius:6px;border:1px solid #E7E0D7;">
                    <span style="display:inline-block;background:#E7E0D7;color:#1C1917;font-size:11px;font-weight:700;padding:2px 8px;border-radius:3px;margin-bottom:8px;">PHỤ KIỆN CƠ KHÍ (MÔ PHỎNG)</span>
                    <strong style="color:#1C1917;font-size:15px;display:block;margin-bottom:6px;">Phụ Kiện Cơ Khí &amp; Giảm Chấn</strong>
                    <p style="font-size:13px;color:#69645D;line-height:1.6;margin:0;">Bản lề giảm chấn, ray trượt ngăn kéo, piston trợ lực theo tiêu chuẩn bảo hành của nhà sản xuất phụ kiện.</p>
                </div>
            </div>
            <p style="font-size:13.5px;color:#69645D;line-height:1.6;margin:0;">✦ <strong>Hỗ trợ bảo dưỡng trọn đời:</strong> Hết thời hạn bảo hành miễn phí, DeSpace vẫn tiếp tục hỗ trợ căn chỉnh phụ kiện và cung cấp linh kiện thay thế với chi phí gốc cho mọi khách hàng thân thiết.</p>
        </section>

        <!-- Section 3: Exclusions -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">3. Các Trường Hợp Từ Chối Bảo Hành Miễn Phí</h3>
            <ul style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0;">
                <li>Hao mòn tự nhiên theo thời gian sử dụng (bề mặt sơn bị cọ xát bởi vật nhọn kim loại, đệm mút lún dãn tự nhiên sau nhiều năm sử dụng).</li>
                <li>Sử dụng sai công năng hoặc vượt quá tải trọng thiết kế cho phép của sản phẩm.</li>
                <li>Tác động tiêu cực từ môi trường bảo quản: đặt sản phẩm tại nơi ngập nước, ẩm ướt kéo dài do ngấm tường, phơi trực tiếp dưới ánh nắng mặt trời gắt hoặc lau chùi bằng dung dịch tẩy rửa hóa chất có tính ăn mòn mạnh.</li>
                <li>Sản phẩm đã bị tháo rời hoặc tự ý sửa chữa kết cấu bởi đơn vị kỹ thuật bên ngoài mà không có sự đồng ý của DeSpace.</li>
            </ul>
        </section>

        <!-- Section 4: Warranty Claim Process -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">4. Quy Trình Kích Hoạt &amp; Yêu Cầu Bảo Hành</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-top:14px;margin-bottom:18px;">
                <div style="background:#fff;padding:16px;border-radius:6px;border:1px solid #E7E0D7;">
                    <strong style="color:#C5A880;font-size:18px;display:block;margin-bottom:4px;">01</strong>
                    <strong style="color:#1C1917;font-size:14px;display:block;margin-bottom:6px;">Liên hệ Hỗ trợ</strong>
                    <p style="font-size:12.5px;color:#69645D;line-height:1.5;margin:0;">Gửi yêu cầu qua biểu mẫu liên hệ hoặc email mô phỏng <strong>contact@despace.test</strong> (Kịch bản đồ án).</p>
                </div>
                <div style="background:#fff;padding:16px;border-radius:6px;border:1px solid #E7E0D7;">
                    <strong style="color:#C5A880;font-size:18px;display:block;margin-bottom:4px;">02</strong>
                    <strong style="color:#1C1917;font-size:14px;display:block;margin-bottom:6px;">Cung cấp Hình ảnh</strong>
                    <p style="font-size:12.5px;color:#69645D;line-height:1.5;margin:0;">Gửi số điện thoại mua hàng kèm 2 – 3 ảnh/video quay rõ hiện trạng cần xử lý.</p>
                </div>
                <div style="background:#fff;padding:16px;border-radius:6px;border:1px solid #E7E0D7;">
                    <strong style="color:#C5A880;font-size:18px;display:block;margin-bottom:4px;">03</strong>
                    <strong style="color:#1C1917;font-size:14px;display:block;margin-bottom:6px;">Xử lý Tận nơi</strong>
                    <p style="font-size:12.5px;color:#69645D;line-height:1.5;margin:0;">Kỹ thuật viên phản hồi trong 24h và đặt lịch đến kiểm tra, xử lý tại nhà (Hà Nội).</p>
                </div>
            </div>

            <!-- Course Project Simulation Note -->
            <div style="background:#FAF7F2;border-left:4px solid #b8845a;padding:14px 18px;border-radius:4px;font-size:13px;color:#69645D;line-height:1.6;">
                <strong>Lưu ý mô phỏng đồ án:</strong> Chính sách đổi trả và quy trình bảo hành được mô phỏng nhằm phục vụ hoàn thiện trải nghiệm người dùng trong đồ án DeSpace. Các trường hợp phát sinh thực tế sẽ được hỗ trợ trao đổi qua kênh liên hệ trực tiếp của nhóm tác giả.
            </div>
        </section>
    </div>
</div>
<!-- /wp:html -->
HTML
        ),
        15 => array(
            'slug'    => 'chinh-sach-bao-mat',
            'title'   => 'Chính sách bảo mật',
            'status'  => 'publish',
            'content' => <<<'HTML'
<!-- wp:html -->
<div class="f-wrap ds-policy-page-wrap" style="max-width:960px;margin:0 auto;padding-top:20px;padding-bottom:60px;">
    <div style="margin-bottom:32px;">
        <span class="f-kicker" style="color:#b8845a;font-weight:700;letter-spacing:2px;text-transform:uppercase;font-size:11px;">BẢO VỆ THÔNG TIN KHÁCH HÀNG</span>
        <h2 style="font-family:var(--f-font-editorial, serif);font-size:clamp(28px,3.5vw,38px);margin:8px 0 14px;">Chính Sách Bảo Mật Quyền Riêng Tư</h2>
        <p style="color:#69645D;font-size:14px;line-height:1.7;">DeSpace coi trọng quyền riêng tư và cam kết bảo vệ an toàn tuyệt đối các dữ liệu cá nhân của khách hàng khi truy cập và mua sắm trên nền tảng trực tuyến của chúng tôi.</p>
    </div>

    <!-- Academic Simulation Disclaimer -->
    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;padding:14px 18px;margin-bottom:28px;font-size:13px;color:#92400E;line-height:1.6;">
        <strong>Thông báo đồ án:</strong> Chính sách bảo mật này được biên soạn làm mẫu tài liệu cho đồ án môn học DeSpace, thể hiện quy trình tuân thủ bảo vệ dữ liệu cá nhân khách hàng trong thương mại điện tử.
    </div>

    <div style="display:grid;gap:28px;">
        <!-- Section 1 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">1. Mục Đích &amp; Phạm Vi Thu Thập Thông Tin</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:12px;">Khi quý khách đăng ký tài khoản thành viên, đặt hàng trực tuyến hoặc gửi biểu mẫu đăng ký tư vấn tại showroom, chúng tôi thu thập các thông tin cần thiết bao gồm:</p>
            <ul style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0 0 14px;">
                <li>Họ và tên, số điện thoại liên lạc, địa chỉ email.</li>
                <li>Địa chỉ nhận hàng (để thực hiện dịch vụ giao nhận và thi công lắp đặt).</li>
                <li>Thông tin đơn hàng, lịch sử giao dịch và các ghi chú tư vấn không gian nội thất.</li>
            </ul>
            <p style="font-size:13.5px;color:#69645D;line-height:1.6;margin:0;"><strong>Mục đích sử dụng:</strong> Xử lý đơn hàng, thông báo tiến độ chế tác, hỗ trợ bảo hành theo chính sách (mô phỏng đồ án) và giải đáp các thắc mắc kỹ thuật của quý khách.</p>
        </section>

        <!-- Section 2 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">2. Cam Kết Bảo Vệ Dữ Liệu &amp; Không Chia Sẻ Trái Phép</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:12px;">DeSpace cam kết tuyệt đối bảo vệ dữ liệu khách hàng theo các nguyên tắc nghiêm ngặt:</p>
            <ul style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0;">
                <li><strong>Không kinh doanh dữ liệu:</strong> Chúng tôi tuyệt đối không bán, cho thuê hoặc chuyển nhượng thông tin cá nhân của quý khách cho bất kỳ bên thứ ba nào vì mục đích quảng cáo thương mại.</li>
                <li><strong>Phạm vi chia sẻ dịch vụ:</strong> Chỉ cung cấp họ tên, số điện thoại và địa chỉ cho nhân viên giao hàng hoặc đơn vị vận chuyển đối tác với mục đích duy nhất là hoàn tất việc giao nhận sản phẩm tận nhà.</li>
            </ul>
        </section>

        <!-- Section 3 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">3. Bảo Mật Thanh Toán &amp; Mã Hóa An Toàn</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:12px;">Toàn bộ kết nối giữa trình duyệt của quý khách và máy chủ DeSpace được mã hóa bằng chứng thư bảo mật SSL tiêu chuẩn (HTTPS 256-bit). DeSpace <strong>không lưu trữ trực tiếp bất kỳ thông tin số thẻ tín dụng hay mật khẩu ngân hàng</strong> của khách hàng trên hệ thống; các giao dịch thanh toán trực tuyến đều được thực hiện qua các cổng trung gian đạt chứng nhận an toàn quốc tế PCI-DSS.</p>
        </section>

        <!-- Section 4 -->
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">4. Quyền Của Khách Hàng Đối Với Dữ Liệu Cá Nhân</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin-bottom:12px;">Quý khách có toàn quyền kiểm soát dữ liệu cá nhân của mình:</p>
            <ul style="padding-left:20px;color:#69645D;font-size:14px;line-height:1.75;margin:0 0 14px;">
                <li>Đăng nhập vào mục <a href="/my-account/" style="color:#9A6745;text-decoration:underline;">Tài khoản của tôi</a> để kiểm tra, cập nhật hoặc sửa đổi địa chỉ giao hàng và thông tin liên hệ.</li>
                <li>Yêu cầu DeSpace trích xuất bản sao dữ liệu hoặc xóa hoàn toàn dữ liệu tài khoản khỏi hệ thống máy chủ bằng cách gửi email tới <span style="color:#9A6745;text-decoration:underline;">contact@despace.test (Mô phỏng đồ án)</span>.</li>
            </ul>

            <div style="background:#F3F4F6;border-left:4px solid #6B7280;padding:14px 18px;border-radius:4px;font-size:13px;color:#374151;line-height:1.6;">
                <strong>Ghi chú:</strong> Văn bản chính sách bảo mật này được công bố công khai nhằm bảo vệ quyền lợi hợp pháp của người tiêu dùng khi giao dịch trên website nội thất DeSpace. Nếu bạn có bất kỳ câu hỏi nào về chính sách quyền riêng tư, xin vui lòng gửi thư về <strong>contact@despace.test (Mô phỏng đồ án)</strong>.
            </div>
        </section>
    </div>
</div>
<!-- /wp:html -->
HTML
        ),
        16 => array(
            'slug'    => 'dieu-khoan-mua-ban',
            'title'   => 'Điều khoản mua bán & Dịch vụ',
            'status'  => 'publish',
            'content' => <<<'HTML'
<!-- wp:html -->
<div class="f-wrap ds-policy-page-wrap" style="max-width:960px;margin:0 auto;padding-top:20px;padding-bottom:60px;">
    <div style="margin-bottom:32px;">
        <span class="f-kicker" style="color:#b8845a;font-weight:700;letter-spacing:2px;text-transform:uppercase;font-size:11px;">ĐIỀU KHOẢN VÀ ĐIỀU KIỆN</span>
        <h2 style="font-family:var(--f-font-editorial, serif);font-size:clamp(28px,3.5vw,38px);margin:8px 0 14px;">Điều Khoản Mua Bán &amp; Dịch Vụ DeSpace</h2>
        <p style="color:#69645D;font-size:14px;line-height:1.7;">Các điều khoản dưới đây quy định quyền lợi và trách nhiệm giữa khách hàng và thương hiệu nội thất DeSpace khi tiến hành đặt mua sản phẩm trên website hoặc ký kết đơn hàng tại showroom.</p>
    </div>

    <!-- Academic Simulation Disclaimer -->
    <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:6px;padding:14px 18px;margin-bottom:28px;font-size:13px;color:#92400E;line-height:1.6;">
        <strong>Lưu ý kịch bản đồ án:</strong> Bản điều khoản mua bán và dịch vụ này được biên soạn cho đồ án môn học DeSpace, mang tính chất mô phỏng quy trình giao kết và giải quyết khiếu nại, không có giá trị giao kết hợp đồng kinh tế thực tế trước pháp luật.
    </div>

    <div style="display:grid;gap:28px;">
        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">1. Xác Nhận Đơn Hàng &amp; Hợp Đồng</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin:0;">Đơn hàng đặt trực tuyến trên website chỉ chính thức có hiệu lực sau khi chuyên viên DeSpace liên hệ trực tiếp qua điện thoại hoặc Zalo để xác nhận lại thông tin sản phẩm, kích thước thực tế, địa chỉ giao nhận và thời gian thi công lắp đặt.</p>
        </section>

        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">2. Giá Bán &amp; Hình Thức Thanh Toán</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin:0;">Giá hiển thị trên website là giá niêm yết bằng Việt Nam Đồng (VNĐ). Khách hàng có thể thanh toán qua các phương thức được hỗ trợ: Tiền mặt khi giao hàng (COD), Chuyển khoản ngân hàng hoặc Thẻ thanh toán quốc tế. Đối với các đơn hàng chế tác kích thước theo yêu cầu, khách hàng đặt cọc theo thỏa thuận trên hợp đồng mua bán.</p>
        </section>

        <section style="background:#FAF7F2;padding:28px;border-radius:8px;border:1px solid #E7E0D7;">
            <h3 style="font-family:var(--f-font-editorial, serif);font-size:22px;color:#1C1917;margin:0 0 14px;">3. Giao Nhận, Lắp Đặt &amp; Bảo Hành</h3>
            <p style="font-size:14px;color:#69645D;line-height:1.75;margin:0;">Khách hàng có trách nhiệm đồng kiểm tra tình trạng hàng hóa trước khi ký biên bản bàn giao. Mọi quyền lợi đổi trả và bảo hành kết cấu (mô phỏng đồ án) được áp dụng theo đúng nội dung công bố tại trang <a href="/chinh-sach-giao-hang/" style="color:#9A6745;text-decoration:underline;">Chính sách giao hàng</a> và <a href="/chinh-sach-doi-tra-bao-hanh/" style="color:#9A6745;text-decoration:underline;">Chính sách đổi trả &amp; bảo hành</a>.</p>
        </section>
    </div>
</div>
<!-- /wp:html -->
HTML
        ),
    );
