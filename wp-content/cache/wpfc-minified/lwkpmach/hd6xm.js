// source --> https://training.netrika.com/wp-content/plugins/masterstudy-lms-learning-management-system/assets/js/courses_filters.js?ver=1 
"use strict";

(function ($) {
  $(document).ready(function () {
    if (!$('.courses_filters').length) return true;
    var $sort = $('.courses_filters .stm_lms_courses_grid__sort select');
    var $container = $sort.closest('.stm_lms_courses_wrapper').find('.stm_lms_courses__archive');
    var $btn = $container.find('.stm_lms_load_more_courses');
    var offset = 0;
    var template = $btn.attr('data-template');
    var args = $btn.attr('data-args');
    $sort.on('change', function (e) {
      var suburl = $btn.attr('data-url');
      var sort_value = $sort.val();
      $btn.attr('data-args', args.replace('}', ',"sort":"' + sort_value + '"}'));
      if ($btn.hasClass('loading')) return false;
      $.ajax({
        url: stm_lms_ajaxurl + suburl,
        dataType: 'json',
        context: this,
        data: {
          offset: offset,
          template: template,
          sort: sort_value,
          args: args,
          action: 'stm_lms_load_content'
        },
        beforeSend: function beforeSend() {
          $btn.addClass('loading');
          $container.addClass('loading');
        },
        complete: function complete(data) {
          data = data['responseJSON'];
          $btn.removeClass('loading');
          $container.removeClass('loading');
          var $pages = $btn.closest('.stm_lms_courses').find('[data-pages]');
          $pages.html(data['content']);
          $pages.attr('data-pages', data['pages']);
          $btn.attr('data-offset', 1);
          hide_button($btn, 1);
        }
      });
    });
    course_switcher();
  });

  function course_switcher() {
    $('.courses_filters__switcher i').on('click', function () {
      var view = $(this).attr('data-view');
      $('.courses_filters__switcher i').removeClass('active');
      $(this).addClass('active');

      if (view === 'grid') {
        $('.stm_lms_courses_wrapper').removeClass('stm_lms_courses_list_view').addClass('stm_lms_courses_grid_view');
      } else {
        $('.stm_lms_courses_wrapper').removeClass('stm_lms_courses_grid_view').addClass('stm_lms_courses_list_view');
      }
    });
  }
})(jQuery);