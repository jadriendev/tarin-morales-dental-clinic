const menuButton = document.getElementById("menuButton");
const topMenu = document.getElementById("topMenu");

const accountInfoCard = document.getElementById("accountInfoCard");
const passwordCard = document.getElementById("passwordCard");

const accountInfoModal = document.getElementById("accountInfoModal");
const passwordModal = document.getElementById("passwordModal");

const closeAccountInfo = document.getElementById("closeAccountInfo");
const closePassword = document.getElementById("closePassword");

function openModal(modal) {
    if (modal) {
        modal.classList.add("show");
        document.body.style.overflow = "hidden";
    }
}

function closeModal(modal) {
    if (modal) {
        modal.classList.remove("show");
        document.body.style.overflow = "";
    }
}

if (menuButton) {
    menuButton.addEventListener("click", function() {
        topMenu.classList.toggle("show");
    });
}

if (accountInfoCard) {
    accountInfoCard.addEventListener("click", function() {
        openModal(accountInfoModal);
    });
}

if (passwordCard) {
    passwordCard.addEventListener("click", function() {
        openModal(passwordModal);
    });
}

if (closeAccountInfo) {
    closeAccountInfo.addEventListener("click", function() {
        closeModal(accountInfoModal);
    });
}

if (closePassword) {
    closePassword.addEventListener("click", function() {
        closeModal(passwordModal);
    });
}

window.addEventListener("click", function(event) {

    if (event.target === accountInfoModal) {
        closeModal(accountInfoModal);
    }

    if (event.target === passwordModal) {
        closeModal(passwordModal);
    }

    if (
        topMenu &&
        menuButton &&
        !menuButton.contains(event.target) &&
        !topMenu.contains(event.target)
    ) {
        topMenu.classList.remove("show");
    }

});

window.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        closeModal(accountInfoModal);
        closeModal(passwordModal);

        if (topMenu) {
            topMenu.classList.remove("show");
        }
    }

});