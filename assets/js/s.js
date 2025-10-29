// Toggle Search Box
function toggleSearch() {
    const searchBoxes = document.querySelectorAll('.search-box');
    searchBoxes.forEach(box => {
        box.classList.toggle('show');
    });
}

// Close search box when clicking outside
document.addEventListener('click', function (event) {
    const searchIcons = document.querySelectorAll('.search-icon');
    const searchBoxes = document.querySelectorAll('.search-box');

    let clickedInside = false;
    searchIcons.forEach(icon => {
        if (icon.contains(event.target)) clickedInside = true;
    });
    searchBoxes.forEach(box => {
        if (box.contains(event.target)) clickedInside = true;
    });

    if (!clickedInside) {
        searchBoxes.forEach(box => box.classList.remove('show'));
    }
});