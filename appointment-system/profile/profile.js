function toggleAccountMenu() {
    const accountMenu = document.getElementById("accountMenu");

    if (accountMenu) {
        accountMenu.classList.toggle("show");
    }
}

window.addEventListener("click", function(event) {
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
        const accountMenu = document.getElementById("accountMenu");

        if (accountMenu) {
            accountMenu.classList.remove("show");
        }
    }
});