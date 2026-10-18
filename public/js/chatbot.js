
console.log("Đang kiểm tra nạp file Chatbot JS...");

function initChatbot() {
    // Ngăn khởi tạo sự kiện nhiều lần
    if (window.chatbotInitialized) {
        console.log("Chatbot JS bị gọi trùng, đã tự động ngăn chặn!");
        return;
    }

    // Lấy các phần tử HTML
    const btnToggle = document.getElementById('btn-chatbot-toggle');
    const btnClose = document.getElementById('btn-chatbot-close');
    const chatWindow = document.getElementById('chatbot-window');
    const chatForm = document.getElementById('chatbot-form');
    const chatInput = document.getElementById('chatbot-input');
    const chatMessages = document.getElementById('chatbot-messages');

    // Màn hình gợi ý ban đầu
    const welcomeScreen = document.getElementById('chatbot-welcome-screen');

    // Kiểm tra HTML trước khi đánh dấu đã khởi tạo
    if (
        !btnToggle ||
        !btnClose ||
        !chatWindow ||
        !chatForm ||
        !chatInput ||
        !chatMessages
    ) {
        console.error(
            "Không tìm thấy đủ phần tử HTML của chatbot!",
            {
                btnToggle,
                btnClose,
                chatWindow,
                chatForm,
                chatInput,
                chatMessages
            }
        );
        return;
    }

    // Đánh dấu đã khởi tạo sau khi xác nhận đủ phần tử
    window.chatbotInitialized = true;
    console.log("Khởi tạo Chatbot thành công!");

    // ---------------------------------------------------------
    // MỞ / ĐÓNG KHUNG CHAT
    // ---------------------------------------------------------

    btnToggle.addEventListener('click', function (e) {
        e.preventDefault();

        chatWindow.classList.toggle('d-none');

        if (!chatWindow.classList.contains('d-none')) {
            chatInput.focus();
        }
    });

    btnClose.addEventListener('click', function (e) {
        e.preventDefault();
        chatWindow.classList.add('d-none');
    });

    // ---------------------------------------------------------
    // XỬ LÝ CÁC NÚT CÂU HỎI GỢI Ý
    // ---------------------------------------------------------

    const suggestionBtns = document.querySelectorAll('.btn-suggestion');

    suggestionBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            chatInput.value = this.innerText.trim();

            // Gửi câu hỏi qua form hiện có
            chatForm.dispatchEvent(
                new Event('submit', {
                    bubbles: true,
                    cancelable: true
                })
            );
        });
    });

    // ---------------------------------------------------------
    // PHIÊN LÀM VIỆC CHATBOT
    // ---------------------------------------------------------

    let maPhienChat = localStorage.getItem('ChatbotSession_Badminton');

    // ---------------------------------------------------------
    // GỬI TIN NHẮN
    // ---------------------------------------------------------

    chatForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const message = chatInput.value.trim();

        if (!message) {
            return;
        }

        // Ẩn màn hình gợi ý khi khách gửi tin nhắn đầu tiên
        if (welcomeScreen) {
            welcomeScreen.style.display = 'none';
        }

        // 1. Hiển thị tin nhắn của khách
        // Dùng textContent để tránh chèn HTML không an toàn
        const userMessage = document.createElement('div');
        userMessage.className =
            'd-flex flex-column align-items-end mb-3';

        const userBubble = document.createElement('div');
        userBubble.className =
            'bg-primary text-white p-2 px-3 shadow-sm';
        userBubble.style.maxWidth = '85%';
        userBubble.style.borderRadius = '15px 15px 0 15px';
        userBubble.textContent = message;

        userMessage.appendChild(userBubble);
        chatMessages.appendChild(userMessage);

        chatInput.value = '';
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // 2. Hiệu ứng chatbot đang xử lý
        const typingId = 'typing-' + Date.now();

        const typingMessage = document.createElement('div');
        typingMessage.id = typingId;
        typingMessage.className =
            'd-flex flex-column align-items-start mb-3';

        typingMessage.innerHTML = `
            <div class="bg-white text-muted p-2 px-3 rounded-3 shadow-sm border"
                 style="max-width: 85%; border-radius: 15px 15px 15px 0;">
                <span class="spinner-grow spinner-grow-sm text-primary"
                      role="status"
                      style="width: 10px; height: 10px;"></span>
                Đang xử lý...
            </div>
        `;

        chatMessages.appendChild(typingMessage);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // 3. Lấy phiên bản Standard hoặc Pro
        const versionInput = document.querySelector(
            'input[name="bot_version"]:checked'
        );

        const botVersion = versionInput
            ? versionInput.value
            : 'standard';

        // Lấy thông tin khách hàng từ LocalStorage
        let idKhachHang = null;

        try {
            const user = JSON.parse(localStorage.getItem('user'));

            if (user && user.ID_KhachHang) {
                idKhachHang = user.ID_KhachHang;
            }
        } catch (err) {
            console.warn('Không đọc được thông tin người dùng:', err);
        }

        // Hàm xóa hiệu ứng đang xử lý
        function removeTypingIndicator() {
            const typingElement = document.getElementById(typingId);

            if (typingElement) {
                typingElement.remove();
            }
        }

        // 4. Gọi REST API Laravel
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

            removeTypingIndicator();

            if (!response.ok) {
                throw new Error(
                    data.message ||
                    'Máy chủ trả về lỗi HTTP ' + response.status
                );
            }

            if (data.success) {
                // Lưu mã phiên chat
                if (!maPhienChat && data.maPhienChat) {
                    maPhienChat = data.maPhienChat;

                    localStorage.setItem(
                        'ChatbotSession_Badminton',
                        maPhienChat
                    );
                }

                // Hiển thị câu trả lời của chatbot
                const botMessage = document.createElement('div');
                botMessage.className =
                    'd-flex flex-column align-items-start mb-3';

                const botBubble = document.createElement('div');
                botBubble.className =
                    'bg-white p-2 px-3 shadow-sm border';
                botBubble.style.maxWidth = '85%';
                botBubble.style.borderRadius = '15px 15px 15px 0';
                botBubble.style.lineHeight = '1.5';

                // Cho phép hiển thị nội dung trả lời dạng văn bản an toàn
                botBubble.textContent = data.reply ?? '';

                botMessage.appendChild(botBubble);
                chatMessages.appendChild(botMessage);
            } else {
                const errorMessage = document.createElement('div');
                errorMessage.className = 'text-center mb-3';

                const errorBadge = document.createElement('span');
                errorBadge.className =
                    'badge bg-warning text-dark rounded-pill px-3 py-2 fw-normal';
                errorBadge.textContent =
                    data.message ||
                    data.reply ||
                    'Chatbot chưa thể trả lời câu hỏi này.';

                errorMessage.appendChild(errorBadge);
                chatMessages.appendChild(errorMessage);
            }
        } catch (error) {
            removeTypingIndicator();

            console.error('Lỗi gọi API chatbot:', error);

            const errorMessage = document.createElement('div');
            errorMessage.className = 'text-center mb-3';

            const errorBadge = document.createElement('span');
            errorBadge.className =
                'badge bg-danger rounded-pill px-3 py-2 fw-normal';
            errorBadge.textContent =
                'Lỗi kết nối máy chủ. Vui lòng thử lại!';

            errorMessage.appendChild(errorBadge);
            chatMessages.appendChild(errorMessage);
        }

        chatMessages.scrollTop = chatMessages.scrollHeight;
    });
}

// Hỗ trợ cả trường hợp file JS được tải trước hoặc sau DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChatbot, {
        once: true
    });
} else {
    initChatbot();
}
