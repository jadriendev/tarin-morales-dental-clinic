function toggleAccountMenu() {
    const accountMenu = document.getElementById("accountMenu");

    if (accountMenu) {
        accountMenu.classList.toggle("show");
    }
}

function enableEditing() {
    const editableFields = document.querySelectorAll(".editable-field");
    const editButton = document.getElementById("editButton");
    const saveButton = document.getElementById("saveButton");

    editableFields.forEach(function(field) {
        field.readOnly = false;
        field.classList.add("editing");
    });

    if (editButton) {
        editButton.style.display = "none";
    }

    if (saveButton) {
        saveButton.disabled = false;
    }

    if (editableFields.length > 0) {
        editableFields[0].focus();
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