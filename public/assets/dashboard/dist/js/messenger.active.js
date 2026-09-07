(function ($) {
    "use strict"; // Start of use strict
    ['chat-list__in', 'chat-list__sidebar--right'].forEach(function (className) {
        new PerfectScrollbar($('.' + className)[0]);
    });

    // $('[data-bs-toggle="popover"]').popover({
    //     html: true
    // });

    $('.change-bg-color label').on('click', function () {
        var color = $(this).data('color');
        $('.message-content').removeClass(function (index, css) {
            return (css.match(/(^|\s)bg-\S+/g) || []).join(' ');
        }).addClass('bg-text-' + color);
    });

    var elements = { 'autobot': false, 'manual': true, 'switcher': null };
    ['autobot', 'manual', 'switcher'].forEach(function (id) {
        var element = document.getElementById(id);
        element.addEventListener('click', function () {
            if (id === 'switcher') {
                elements['manual'].classList.toggle('toggler--is-active');
                elements['autobot'].classList.toggle('toggler--is-active');
            } else {
                elements['switcher'].checked = elements[id];
                elements[id === 'autobot' ? 'manual' : 'autobot'].classList.remove('toggler--is-active');
                elements[id].classList.add('toggler--is-active');
            }
        });
        elements[id] = element;
    });

    $(".chat-header").each(function () {
        $(".search-btn", this).on("click", function (e) {
            e.preventDefault();
            $(".conversation-search").slideToggle();
        });
    });

    $(".close-search").on("click", function () {
        $(".conversation-search").slideUp();
    });

    $('.chat-overlay, .chat-list .item-list').on('click', function () {
        $('.chat-list__sidebar, .chat-list__sidebar--right').removeClass('active');
        $('.chat-overlay').removeClass('active');
    });

    $('.chat-sidebar-collapse, .chat-settings-collapse').on('click', function () {
        var sidebarClass = $(this).hasClass('chat-sidebar-collapse') ? 'chat-list__sidebar' : 'chat-list__sidebar--right';
        $('.' + sidebarClass).addClass('active');
        $('.chat-overlay').addClass('active');
        $('.collapse.in').toggleClass('in');
    });

})(jQuery);