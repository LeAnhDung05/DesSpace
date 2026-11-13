(function () {
  "use strict";
  var toggle = document.querySelector(".ds-menu-toggle");
  var nav = document.querySelector(".ds-nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.textContent = open ? "×" : "☰";
    });
  }
})();
