const menuBtn = document.getElementById("menuBtn");
const mobileMenu = document.getElementById("mobileMenu");
const menuIcon = document.getElementById("menuIcon");

menuBtn.addEventListener("click", () => {

    mobileMenu.classList.toggle("max-h-0");
    mobileMenu.classList.toggle("max-h-[600px]");
    mobileMenu.classList.toggle("opacity-0");
    mobileMenu.classList.toggle("opacity-100");

    if(menuIcon.classList.contains("fa-bars")){
        menuIcon.classList.remove("fa-bars");
        menuIcon.classList.add("fa-xmark");
    }else{
        menuIcon.classList.remove("fa-xmark");
        menuIcon.classList.add("fa-bars");
    }

});