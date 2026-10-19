console.log("Đang kiểm tra nạp file Chatbot JS...");

document.addEventListener('DOMContentLoaded', function() {
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
    
    // Biến để ẩn khung gợi ý đi khi người dùng bắt đầu chat
    const welcomeScreen = document.getElementById('chatbot-welcome-screen');

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

    // Xử lý khi click vào các nút câu hỏi gợi ý
    const suggestionBtns = document.querySelectorAll('.btn-suggestion');
    suggestionBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // Lấy text của nút, đưa vào input và tự động submit
            chatInput.value = this.innerText.trim();
            chatForm.dispatchEvent(new Event('submit'));
        });
    });

    // Phiên làm việc (Session) để lưu mạch chat
    let maPhienChat = localStorage.getItem('ChatbotSession_Badminton');

    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        // Ẩn màn hình gợi ý ngay khi người dùng bắt đầu chat câu đầu tiên
        if (welcomeScreen) {
            welcomeScreen.style.display = 'none';
        }

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
                    version: 'pro', // Mặc định truyền thẳng 'pro' lên backend, không cần kiểm tra HTML nữa
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