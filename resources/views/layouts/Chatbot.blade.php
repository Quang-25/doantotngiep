<!-- NÚT BẬT TẮT CHATBOT -->
<div id="btn-chatbot-toggle" class="shadow-lg">
    <i class="bi bi-robot fs-2"></i>
</div>

<!-- KHUNG CHATBOT -->
<div id="chatbot-window" class="shadow-lg bg-white rounded-4 d-none" style="display: flex; flex-direction: column; height: 500px; width: 350px;">
    
    <!-- Header: Tên bot (Đã bỏ công tắc) -->
    <div class="bg-primary text-white p-3 d-flex justify-content-between align-items-center" style="border-radius: 1rem 1rem 0 0;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-robot fs-4"></i>
            <div class="d-flex flex-column">
                <span class="fw-bold mb-0" style="font-size: 15px;">Trợ Lý Badminton</span>
                <span style="font-size: 11px; opacity: 0.8;"><i class="bi bi-stars"></i> AI Assistant</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button id="btn-chatbot-history" type="button" class="btn btn-sm btn-light">Lịch sử</button>
            <button id="btn-chatbot-new" type="button" class="btn btn-sm btn-light" title="Bắt đầu cuộc trò chuyện mới" aria-label="Bắt đầu cuộc trò chuyện mới">
                <i class="bi bi-plus-lg"></i>
            </button>
            <button id="btn-chatbot-close" class="btn-close btn-close-white" aria-label="Đóng"></button>
        </div>
    </div>

    <div id="chatbot-history-panel" class="d-none p-3" style="flex: 1; overflow-y: auto; background-color: #fcfcfc;"></div>

    <!-- KHU VỰC HIỂN THỊ TIN NHẮN -->
    <div id="chatbot-messages" class="p-3" style="flex: 1; overflow-y: auto; background-color: #fcfcfc;">
        
        <!-- Màn hình Welcome & Câu hỏi gợi ý -->
        <div id="chatbot-welcome-screen" class="text-center mt-2 mb-4">
            <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-25 text-success rounded-circle mb-2" style="width: 45px; height: 45px;">
                <i class="bi bi-chat-dots fs-4"></i>
            </div>
            <h6 class="fw-bold mb-1">Xin chào! 👋</h6>
            <p class="text-muted mb-3" style="font-size: 12.5px;">Tôi có thể giúp bạn tìm hiểu về cửa hàng và vợt cầu lông</p>
            
            <div class="text-muted mb-2 mt-3" style="font-size: 12px;">Câu hỏi gợi ý:</div>
            
            <div class="d-flex flex-column gap-2 px-2">
                <button class="btn btn-outline-secondary bg-white text-start rounded-4 py-2 px-3 btn-suggestion shadow-sm" style="font-size: 13px; border: 1px solid #e2e8f0; color: #4a5568;">
                    Tư vấn vợt công thủ toàn diện?
                </button>
                <button class="btn btn-outline-secondary bg-white text-start rounded-4 py-2 px-3 btn-suggestion shadow-sm" style="font-size: 13px; border: 1px solid #e2e8f0; color: #4a5568;">
                    Làm sao để tra cứu đơn hàng?
                </button>
                <button class="btn btn-outline-secondary bg-white text-start rounded-4 py-2 px-3 btn-suggestion shadow-sm" style="font-size: 13px; border: 1px solid #e2e8f0; color: #4a5568;">
                    Chính sách bảo hành vợt thế nào?
                </button>
                <button class="btn btn-outline-secondary bg-white text-start rounded-4 py-2 px-3 btn-suggestion shadow-sm" style="font-size: 13px; border: 1px solid #e2e8f0; color: #4a5568;">
                    Cửa hàng hỗ trợ thanh toán qua đâu?
                </button>
            </div>
        </div>
    </div>

    <!-- Vùng nhập câu hỏi -->
    <div class="p-2 bg-white border-top w-100" style="margin-top: auto; border-radius: 0 0 1rem 1rem;">
        <form id="chatbot-form" class="d-flex gap-2 m-0 align-items-center">
            <input type="text" id="chatbot-input" class="form-control rounded-pill px-3 py-2 bg-light" placeholder="Nhập câu hỏi..." autocomplete="off" required style="font-size: 14px; border: 1px solid #cfe2ff;">
            <button type="submit" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; flex-shrink: 0; border: none;">
                <i class="bi bi-send-fill text-white"></i>
            </button>
        </form>
        <div class="text-center mt-1 text-muted" style="font-size: 10px;">Thông tin do AI cung cấp, vui lòng kiểm tra nguồn chính thức</div>
    </div>
</div>

<link rel="stylesheet" href="{{ asset('css/chatbot.css') }}">
<script src="{{ asset('js/chatbot.js') }}"></script>