function toggleMenu() {
    const menu = document.getElementById("topMenu");

    if (menu) {
        menu.classList.toggle("show");
    }
}

function openModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.add("show");
        document.body.style.overflow = "hidden";
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);

    if (modal) {
        modal.classList.remove("show");
        document.body.style.overflow = "";
    }
}

window.addEventListener("click", function(event) {
    const menu = document.getElementById("topMenu");
    const menuContainer = document.querySelector(".menu-container");

    if (
        menu &&
        menu.classList.contains("show") &&
        menuContainer &&
        !menuContainer.contains(event.target)
    ) {
        menu.classList.remove("show");
    }

    if (event.target.classList.contains("modal")) {
        event.target.classList.remove("show");
        document.body.style.overflow = "";
    }
});

window.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        const menu = document.getElementById("topMenu");

        if (menu) {
            menu.classList.remove("show");
        }

        document.querySelectorAll(".modal").forEach(function(modal) {
            modal.classList.remove("show");
        });

        document.body.style.overflow = "";
    }
});