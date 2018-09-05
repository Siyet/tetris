$('.hide_sidebar').on('click', function() {
    event.preventDefault();
    $('.main-sidebar').toggleClass('hidden');
    $('.is-brand').toggleClass('hidden');
    $('.main-content').toggleClass('change_padding');
});