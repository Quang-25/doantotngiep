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
    const btnHistory = document.getElementById('btn-chatbot-history');
    const btnNew = document.getElementById('btn-chatbot-new');
    const chatWindow = document.getElementById('chatbot-window');
    const chatForm = document.getElementById('chatbot-form');
    const chatInput = document.getElementById('chatbot-input');
    const chatMessages = document.getElementById('chatbot-messages');
    const historyPanel = document.getElementById('chatbot-history-panel');

    // Màn hình chào + câu hỏi gợi ý: hiện khi cuộc trò chuyện chưa có tin nhắn
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
    document.querySelectorAll('.btn-suggestion').forEach(btn => {
        btn.addEventListener('click', function() {
            chatInput.value = this.innerText.trim();
            chatForm.requestSubmit();
        });
    });

    // ---------------------------------------------------------
    // LỊCH SỬ CHAT: mỗi cuộc trò chuyện có mã phiên riêng (gửi lên server để chatbot nhớ ngữ cảnh),
    // danh sách cuộc trò chuyện lưu trong trình duyệt, tách theo tài khoản đăng nhập.
    function getCustomerId() {
        try {
            const user = JSON.parse(localStorage.getItem('user'));
            return user && user.ID_KhachHang ? user.ID_KhachHang : null;
        } catch (err) {
            return null;
        }
    }

    const customerId = getCustomerId();
    const storageKey = `ChatbotHistory_Badminton_${customerId || 'guest'}`;
    const activeKey = `ChatbotActive_Badminton_${customerId || 'guest'}`;

    function createId() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function loadConversations() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
            return Array.isArray(saved) ? saved : [];
        } catch (err) {
            return [];
        }
    }

    function saveConversations() {
        try {
            // Giữ tối đa 30 cuộc trò chuyện gần nhất
            conversations.sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt));
            conversations = conversations.slice(0, 30);
            localStorage.setItem(storageKey, JSON.stringify(conversations));
            localStorage.setItem(activeKey, activeConversation.id);
        } catch (err) {
            console.warn('Không lưu được lịch sử chat:', err);
        }
    }

    function createConversation() {
        const conversation = {
            id: createId(),
            title: 'Cuộc trò chuyện mới',
            messages: [],
            updatedAt: new Date().toISOString()
        };
        conversations.unshift(conversation);
        return conversation;
    }

    let conversations = loadConversations();
    let activeConversation = conversations.find(c => c.id === localStorage.getItem(activeKey))
        || conversations[0]
        || createConversation();

    function appendMessage(role, content) {
        const wrapper = document.createElement('div');
        wrapper.className = `d-flex flex-column ${role === 'user' ? 'align-items-end' : 'align-items-start'} mb-3`;
        const bubble = document.createElement('div');
        bubble.className = role === 'user' ? 'bg-primary text-white p-2 px-3 shadow-sm' : 'bg-white p-2 px-3 shadow-sm border';
        bubble.style.maxWidth = '85%';
        bubble.style.lineHeight = '1.5';
        bubble.style.borderRadius = role === 'user' ? '15px 15px 0 15px' : '15px 15px 15px 0';
        if (role === 'user') {
            bubble.textContent = content; // Tin của khách hiển thị dạng chữ thuần
        } else {
            bubble.innerHTML = content;   // Tin của chatbot là HTML do server tạo
        }
        wrapper.appendChild(bubble);
        chatMessages.appendChild(wrapper);
        return wrapper;
    }

    function renderConversation() {
        chatMessages.innerHTML = '';
        if (welcomeScreen) {
            welcomeScreen.style.display = activeConversation.messages.length ? 'none' : '';
            chatMessages.appendChild(welcomeScreen);
        }
        activeConversation.messages.forEach(message => appendMessage(message.role, message.content));
        historyPanel.classList.add('d-none');
        chatMessages.classList.remove('d-none');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function renderHistory() {
        historyPanel.innerHTML = '';
        const title = document.createElement('div');
        title.className = 'fw-bold mb-2';
        title.textContent = 'Lịch sử trò chuyện';
        historyPanel.appendChild(title);

        const savedConversations = conversations.filter(c => c.messages.length);
        if (!savedConversations.length) {
            const empty = document.createElement('p');
            empty.className = 'text-muted text-center mt-3 mb-0';
            empty.style.fontSize = '13px';
            empty.textContent = 'Chưa có cuộc trò chuyện nào.';
            historyPanel.appendChild(empty);
        }

        savedConversations.forEach(conversation => {
            const row = document.createElement('div');
            row.className = 'd-flex gap-1 mb-2';

            const open = document.createElement('button');
            open.type = 'button';
            open.className = 'btn btn-light border text-start flex-grow-1' + (conversation === activeConversation ? ' border-primary' : '');
            open.style.minWidth = '0';
            const name = document.createElement('span');
            name.className = 'd-block fw-semibold text-truncate';
            name.style.fontSize = '13px';
            name.textContent = conversation.title;
            const detail = document.createElement('small');
            detail.className = 'd-block text-muted';
            detail.textContent = `${conversation.messages.length} tin nhắn · ${new Date(conversation.updatedAt).toLocaleString('vi-VN')}`;
            open.append(name, detail);
            open.addEventListener('click', () => {
                activeConversation = conversation;
                saveConversations();
                renderConversation();
            });

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-light border text-danger';
            remove.title = 'Xóa cuộc trò chuyện';
            remove.setAttribute('aria-label', 'Xóa cuộc trò chuyện');
            remove.innerHTML = '<i class="bi bi-trash"></i>';
            remove.addEventListener('click', () => {
                conversations = conversations.filter(c => c !== conversation);
                if (activeConversation === conversation) {
                    activeConversation = conversations[0] || createConversation();
                }
                saveConversations();
                renderHistory();
            });

            row.append(open, remove);
            historyPanel.appendChild(row);
        });

        chatMessages.classList.add('d-none');
        historyPanel.classList.remove('d-none');
    }

    btnHistory.addEventListener('click', () => {
        if (historyPanel.classList.contains('d-none')) {
            renderHistory();
        } else {
            renderConversation();
        }
    });

    btnNew.addEventListener('click', () => {
        activeConversation = activeConversation.messages.length ? createConversation() : activeConversation;
        saveConversations();
        renderConversation();
        chatInput.focus();
    });

    renderConversation();

    // ---------------------------------------------------------
    // GỬI TIN NHẮN
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message || chatForm.dataset.sending === 'true') return;

        const conversation = activeConversation;
        conversation.messages.push({ role: 'user', content: message });
        conversation.updatedAt = new Date().toISOString();
        if (conversation.title === 'Cuộc trò chuyện mới') {
            conversation.title = message.length > 40 ? message.slice(0, 40) + '…' : message;
        }
        saveConversations();
        renderConversation();
        chatInput.value = '';
        chatForm.dataset.sending = 'true';

        // Hiệu ứng chatbot đang trả lời
        const typing = appendMessage('assistant', '<span class="spinner-grow spinner-grow-sm text-primary" role="status" style="width: 10px; height: 10px;"></span> Đang xử lý...');
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            const response = await fetch('/api/chatbot/tu-van', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    version: 'pro',
                    maPhienChat: conversation.id,
                    idKhachHang: customerId
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Chatbot request failed');
            }

            conversation.messages.push({ role: 'assistant', content: data.reply });
            conversation.updatedAt = new Date().toISOString();
            saveConversations();
            if (activeConversation === conversation) renderConversation();
        } catch (error) {
            console.error('Chatbot request failed:', error);
            typing.remove();
            if (activeConversation === conversation) {
                const errorMessage = document.createElement('div');
                errorMessage.className = 'text-center mb-3';
                errorMessage.innerHTML = '<span class="badge bg-danger rounded-pill px-3 py-2 fw-normal">Lỗi kết nối máy chủ. Vui lòng thử lại!</span>';
                chatMessages.appendChild(errorMessage);
            }
        } finally {
            chatForm.dataset.sending = 'false';
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    });
});
