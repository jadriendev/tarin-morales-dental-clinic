const chatToggle = document.getElementById("chatToggle");
    const chatWindow = document.getElementById("chatWindow");
    const closeChat = document.getElementById("closeChat");

    chatToggle.addEventListener("click", () => {
        chatWindow.classList.toggle("hidden");
    });

    closeChat.addEventListener("click", () => {
        chatWindow.classList.add("hidden");
    });