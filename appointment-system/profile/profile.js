function toggleMenu() {
    const menu = document.getElementById("topMenu");

    if (menu) {
        menu.classList.toggle("show");
    }
}

function toggleAccountMenu() {
    const accountMenu = document.getElementById("accountMenu");

    if (accountMenu) {
        accountMenu.classList.toggle("show");
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

    const accountContainer = document.querySelector(".account-container");
    const accountMenu = document.getElementById("accountMenu");

    if (
        accountContainer &&
        accountMenu &&
        !accountContainer.contains(event.target)
    ) {
        accountMenu.classList.remove("show");
    }
});

window.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        const menu = document.getElementById("topMenu");
        const accountMenu = document.getElementById("accountMenu");

        if (menu) {
            menu.classList.remove("show");
        }

        if (accountMenu) {
            accountMenu.classList.remove("show");
        }
    }
});