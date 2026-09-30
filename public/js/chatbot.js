console.log("Đang kiểm tra nạp file Chatbot JS...");

document.addEventListener('DOMContentLoaded', function() {
    // ---------------------------------------------------------
    // CHỐT CHẶN TRÁNH XUNG ĐỘT: Nếu đã chạy rồi thì dừng lại ngay
    // ---------------------------------------------------------
    if (window.chatbotInitialized) {
        console.log("Chatbot JS bị gọi trùng, đã tự động ngăn chặn!");
        return;
    }
    window.chatbotInitialized = true;
    console.log("Khởi tạo Chatbot thành công!");
    // ---------------------------------------------------------

    const btnToggle = document.getElementById('btn-chatbot-toggle');
    const btnClose = document.getElementById('btn-chatbot-close');
    const chatWindow = document.getElementById('chatbot-window');
    const chatForm = document.getElementById('chatbot-form');
    const chatInput = document.getElementById('chatbot-input');
    const chatMessages = document.getElementById('chatbot-messages');

    // Mở / Đóng khung chat
    btnToggle.addEventListener('click', () => {
        chatWindow.classList.toggle('d-none');
        if (!chatWindow.classList.contains('d-none')) {
            chatInput.focus();
        }
    });
    
    btnClose.addEventListener('click', () => {
        chatWindow.classList.add('d-none');
    });

    // Phiên làm việc (Session) để lưu mạch chat
    let maPhienChat = localStorage.getItem('ChatbotSession_Badminton');

    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        // 1. In tin nhắn của Khách lên UI
        chatMessages.innerHTML += `
            <div class="d-flex flex-column align-items-end mb-3">
                <div class="bg-primary text-white p-2 px-3 shadow-sm" style="max-width: 85%; border-radius: 15px 15px 0 15px;">
                    ${message}
                </div>
            </div>`;
        chatInput.value = '';
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // 2. In hiệu ứng Bot đang gõ (Typing Indicator)
        const typingId = 'typing-' + Date.now();
        chatMessages.innerHTML += `
            <div id="${typingId}" class="d-flex flex-column align-items-start mb-3">
                <div class="bg-white text-muted p-2 px-3 rounded-3 shadow-sm border" style="max-width: 85%; border-radius: 15px 15px 15px 0;">
                    <span class="spinner-grow spinner-grow-sm text-primary" role="status" style="width: 10px; height: 10px;"></span> Đang xử lý...
                </div>
            </div>`;
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Lấy phiên bản Standard hay Pro
        const botVersion = document.querySelector('input[name="bot_version"]:checked').value;

        // Kiểm tra xem khách có đăng nhập không (Lấy từ LocalStorage trang web)
        let idKhachHang = null;
        try {
            const user = JSON.parse(localStorage.getItem('user'));
            if (user && user.ID_KhachHang) idKhachHang = user.ID_KhachHang;
        } catch(err) {}

        // 3. GỌI RESTFUL API LÊN LARAVEL
        try {
            const response = await fetch('/api/chatbot/tu-van', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ 
                    message: message, 
                    version: botVersion, 
                    maPhienChat: maPhienChat,
                    idKhachHang: idKhachHang
                })
            });

            const data = await response.json();
            document.getElementById(typingId).remove(); // Xóa chữ "Đang xử lý..."

            if (data.success) {
                // Lưu ID phiên chat vào LocalStorage
                if (!maPhienChat) {
                    maPhienChat = data.maPhienChat;
                    localStorage.setItem('ChatbotSession_Badminton', maPhienChat);
                }

                // 4. In câu trả lời của Server lên UI
                chatMessages.innerHTML += `
                    <div class="d-flex flex-column align-items-start mb-3">
                        <div class="bg-white p-2 px-3 shadow-sm border" style="max-width: 85%; border-radius: 15px 15px 15px 0; line-height: 1.5;">
                            ${data.reply}
                        </div>
                    </div>`;
            }
        } catch (error) {
            document.getElementById(typingId).remove();
            chatMessages.innerHTML += `
                <div class="text-center mb-3">
                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-normal">Lỗi kết nối máy chủ. Vui lòng thử lại!</span>
                </div>`;
        }
        
        chatMessages.scrollTop = chatMessages.scrollHeight; // Tự động cuộn xuống cuối
    });
});