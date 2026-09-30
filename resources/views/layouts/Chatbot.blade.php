<!-- NÚT BẬT TẮT CHATBOT -->
<div id="btn-chatbot-toggle" class="shadow-lg">
    <i class="bi bi-robot fs-2"></i>
</div>

<!-- KHUNG CHATBOT -->
<div id="chatbot-window" class="shadow-lg bg-white rounded-4 d-none">
    
    <!-- Header: Tên bot và Công tắc Standard/Pro -->
    <div class="bg-primary text-white p-3 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-robot fs-4"></i>
            <div class="d-flex flex-column">
                <span class="fw-bold mb-1" style="font-size: 15px;">Trợ Lý Badminton</span>
                
                <!-- Chuyển đổi phiên bản -->
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="bot_version" id="bot_standard" value="standard" checked>
                    <label class="btn btn-outline-light py-0" style="font-size: 11px;" for="bot_standard">Cơ bản</label>

                    <input type="radio" class="btn-check" name="bot_version" id="bot_pro" value="pro">
                    <label class="btn btn-outline-light py-0" style="font-size: 11px;" for="bot_pro"><i class="bi bi-stars"></i> Pro</label>
                </div>
            </div>
        </div>
        <button id="btn-chatbot-close" class="btn-close btn-close-white"></button>
    </div>

    <div id="chatbot-messages" class="p-3" style="flex: 1; overflow-y: auto; background-color: #f8f9fa;">
    <div class="d-flex flex-column align-items-start mb-3">
        <div class="bg-white p-2 px-3 rounded-3 shadow-sm border" style="max-width: 85%;">
            Xin chào! Tôi có thể tư vấn vợt theo trình độ, tìm mã đơn hàng, hoặc giải đáp thắc mắc. Bạn cần giúp gì ạ?
        </div>
    </div>
    </div>
    <!-- Vùng nhập câu hỏi -->
    <!-- Vùng nhập câu hỏi -->
<div class="p-2 bg-white border-top w-100" style="margin-top: auto;">
    <form id="chatbot-form" class="d-flex gap-2 m-0">
        <input type="text" id="chatbot-input" class="form-control rounded-pill px-3" placeholder="Nhập câu hỏi tại đây..." autocomplete="off" required style="font-size: 14px;">
        <button type="submit" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; flex-shrink: 0;">
            <i class="bi bi-send-fill"></i>
        </button>
    </form>
</div>

<!-- LIÊN KẾT ĐẾN CSS VÀ JS (Sử dụng asset của Laravel) -->
<link rel="stylesheet" href="{{ asset('css/chatbot.css') }}">
<script src="{{ asset('js/chatbot.js') }}"></script>