<?php

STM_LMS_User::init();

class STM_LMS_User
{

    public static function init()
    {
        add_action('wp_ajax_stm_lms_login', 'STM_LMS_User::stm_lms_login');
        add_action('wp_ajax_nopriv_stm_lms_login', 'STM_LMS_User::stm_lms_login');

        add_action('wp_ajax_stm_lms_logout', 'STM_LMS_User::stm_lms_logout');

        add_action('wp_ajax_stm_lms_register', 'STM_LMS_User::stm_lms_register');
        add_action('wp_ajax_nopriv_stm_lms_register', 'STM_LMS_User::stm_lms_register');

        add_action('wp_ajax_stm_lms_become_instructor', 'STM_LMS_User::apply_for_instructor');

        add_action('wp_ajax_stm_lms_enterprise', 'STM_LMS_User::enterprise');
        add_action('wp_ajax_nopriv_stm_lms_enterprise', 'STM_LMS_User::enterprise');

        add_action('wp_ajax_stm_lms_get_user_courses', 'STM_LMS_User::get_user_courses');

        add_action('wp_ajax_stm_lms_get_user_quizzes', 'STM_LMS_User::get_user_quizzes');

        add_action('wp_ajax_stm_lms_wishlist', 'STM_LMS_User::wishlist');

        add_action("wsl_hook_process_login_before_wp_safe_redirect", "STM_LMS_User::wsl_new_register_redirect_url", 100, 4);

        add_action('wp_login', 'STM_LMS_User::user_logged_in', 100, 2);

        add_action('show_user_profile', "STM_LMS_User::extra_fields_display");
        add_action('edit_user_profile', 'STM_LMS_User::extra_fields_display');

        add_action('personal_options_update', 'STM_LMS_User::save_extra_fields');
        add_action('edit_user_profile_update', 'STM_LMS_User::save_extra_fields');

        add_action('wp_ajax_stm_lms_save_user_info', 'STM_LMS_User::save_user_info');

        add_action('wp_ajax_stm_lms_lost_password', 'STM_LMS_User::stm_lms_lost_password');
        add_action('wp_ajax_nopriv_stm_lms_lost_password', 'STM_LMS_User::stm_lms_lost_password');

        add_action('wp_ajax_stm_lms_change_avatar', 'STM_LMS_User::stm_lms_change_avatar');
        add_action('wp_ajax_stm_lms_delete_avatar', 'STM_LMS_User::stm_lms_delete_avatar');

        add_action('stm_lms_redirect_user', 'STM_LMS_User::redirect');

        if (!empty($_GET['user_token'])) {
            add_action('init', 'STM_LMS_User::verify_user');
        }

        add_action('after_setup_theme', 'STM_LMS_User::remove_admin_bar');

        add_action('wp_ajax_stm_lms_restore_password', 'STM_LMS_User::stm_lms_restore_password');
        add_action('wp_ajax_nopriv_stm_lms_restore_password', 'STM_LMS_User::stm_lms_restore_password');

        add_action('stm_lms_after_user_register', 'STM_LMS_User::stm_lms_set_user_role', 10, 2);
//        add_action( 'deleted_user', array('STM_LMS_User::remove_user'), 10 );
////        add_action( 'delete_user', array('STM_LMS_User::remove_user'), 10 );
        /*add_action( 'deleted_user', array('STM_LMS_User::remove_user'), 10 );
        add_action( 'delete_user', array('STM_LMS_User::remove_user'), 10 );*/

        add_filter('stm_lms_curriculum_item_atts', 'STM_LMS_User::curriculum_url', 10, 3);
    }

    static function curriculum_url($atts, $course_id, $item_id)
    {

        if (is_user_logged_in() && STM_LMS_User::has_course_access($course_id, $item_id)) {
            $url = STM_LMS_Course::item_url($course_id, $item_id);
            $atts['data-curriculum-url'] = "data-curriculum-url=\"{$url}\"";
        }

        return $atts;
    }

    function remove_user($user_id)
    {
        stm_lms_get_delete_user_courses($user_id);
    }

    static function remove_admin_bar()
    {
        if (!current_user_can('administrator') && !is_admin()) {
            show_admin_bar(false);
        }
    }

    public static function redirect()
    {
        if (is_user_logged_in()) {
            wp_safe_redirect(STM_LMS_User::user_page_url());
        }
    }

    public static function wsl_new_register_redirect_url($user_id)
    {
        if ($user_id != null) {
            do_action('wsl_clear_user_php_session');
            wp_safe_redirect(STM_LMS_USER::user_page_url($user_id));
            die();
        }
    }

    public static function login_page_url()
    {
        return home_url('/') . STM_LMS_WP_Router::route_urls('login');
    }

    public static function user_page_url($user_id = '', $force = false)
    {
        if (!is_user_logged_in() and !$force) return STM_LMS_User::login_page_url();
        if (empty($user_id)) {
            $user = STM_LMS_User::get_current_user();
            $user_id = $user['id'];
        }
        return home_url('/') . STM_LMS_WP_Router::route_urls('user') . "/{$user_id}";
    }

    public static function user_public_page_url($user_id)
    {
        return home_url('/') . STM_LMS_WP_Router::route_urls('user_profile') . "/{$user_id}";
    }

    public static function stm_lms_login()
    {

        $r = array(
            'status' => 'error'
        );

        $recaptcha_passed = STM_LMS_Helpers::check_recaptcha();
        if (!$recaptcha_passed) {
            $r['message'] = esc_html__('CAPTCHA verification failed.', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);
            die;
        }

        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);


        $user = wp_signon($data, is_ssl());

        if (is_wp_error($user)) {
            $r['message'] = esc_html__('Wrong Username or Password', 'masterstudy-lms-learning-management-system');
        } else {
            $r['user_page'] = STM_LMS_User::user_page_url($user->ID, true);
            $r['message'] = esc_html__('Successfully logged in. Redirecting...', 'masterstudy-lms-learning-management-system');
            $r['status'] = 'success';
        }

        wp_send_json($r);
        die;
    }

    public static function stm_lms_register()
    {

        check_ajax_referer('stm_lms_register', 'nonce');

        $r = array(
            'message' => '',
            'status' => 'error'
        );

        $recaptcha_passed = STM_LMS_Helpers::check_recaptcha();
        if (!$recaptcha_passed) {
            $r['message'] = esc_html__('CAPTCHA verification failed.', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);
            die;
        }

        $fields = array(
            'user_login' => array(
                'label' => esc_html__('Login', 'masterstudy-lms-learning-management-system'),
                'type' => 'text'
            ),
            'user_email' => array(
                'label' => esc_html__('E-mail', 'masterstudy-lms-learning-management-system'),
                'type' => 'email'
            ),
            'user_password' => array(
                'label' => esc_html__('Password', 'masterstudy-lms-learning-management-system'),
                'type' => 'text'
            ),
            'user_password_re' => array(
                'label' => esc_html__('Password confirm', 'masterstudy-lms-learning-management-system'),
                'type' => 'text'
            ),
            'privacy_policy' => array(
                'label' => esc_html__('Privacy Policy', 'masterstudy-lms-learning-management-system'),
                'type' => 'text'
            ),
        );

        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);

        foreach ($fields as $field_key => $field) {
            if (empty($data[$field_key])) {
                $r['message'] = sprintf(esc_html__('%s field is required', 'masterstudy-lms-learning-management-system'), $field['label']);
                wp_send_json($r);
                die;
            } else {
                $data[$field_key] = STM_LMS_Helpers::sanitize_fields($data[$field_key], $field['type']);
                if (empty($data[$field_key])) {
                    $r['message'] = sprintf(esc_html__('Please enter valid %s field', 'masterstudy-lms-learning-management-system'), $field['label']);
                    wp_send_json($r);
                    die;
                }
            }
        }

        extract($data);
        /**
         * @var $user_login ;
         * @var $user_email ;
         * @var $user_password ;
         * @var $user_password_re ;
         */

        /*If password is equal*/
        if ($user_password !== $user_password_re) {

            $r['message'] = esc_html__('Passwords do not match', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);

            die;

        }

        /* If Password shorter than 8 characters*/
        if (strlen($user_password) < 8) {

            $r['message'] = esc_html__('Password must have at least 8 characters', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);

            die;

        }

        /* if Password longer than 20 -for some tricky user try to enter long characters to block input.*/

        if (strlen($user_password) > 20) {

            $r['message'] = esc_html__('Password too long', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);

            die;

        }

        /* if contains letter */

        if (!preg_match("#[a-z]+#", $user_password)) {

            $r['message'] = esc_html__('Password must include at least one letter!', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);

            die;

        }

        /* if contains number */

        if (!preg_match("#[0-9]+#", $user_password)) {

            $r['message'] = esc_html__('Password must include at least one number!', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);

            die;

        }

        /* if contains CAPS */

        if (!preg_match("#[A-Z]+#", $user_password)) {

            $r['message'] = esc_html__('Password must include at least one capital letter!', 'masterstudy-lms-learning-management-system');

            wp_send_json($r);

            die;

        }

        $premoderation = STM_LMS_Options::get_option('user_premoderation', false);

        /*Now we have valid data*/
        $user = wp_create_user($user_login, $user_password, $user_email);

        if (is_wp_error($user)) {
            $r['message'] = $user->get_error_message();
        } else {

            if ($premoderation) {

                self::_handle_premoderation($user, $data, $user_email);

                $r['status'] = 'success';
                $r['message'] = esc_html__('Please follow the instructions sent to your email address.', 'masterstudy-lms-learning-management-system');

            } else {

                self::_register_user($user, $data, $user_email);

                $r['status'] = 'success';
                $r['user_page'] = STM_LMS_User::user_page_url($user, true);
                $r['message'] = esc_html__('Registration completed successfully.', 'masterstudy-lms-learning-management-system');
                do_action('stm_lms_after_user_register', $user, $data);
            }

        }

        wp_send_json($r);
    }

    static function _handle_premoderation($user, $data, $user_email)
    {
        $token = bin2hex(openssl_random_pseudo_bytes(16));

        /*Setting link for 3 days*/
        set_transient($token, $data, 3 * 24 * 60 * 60);

        /*Delete User first and save his data to transient*/
        require_once ABSPATH . 'wp-admin/includes/ms.php';

        wp_delete_user($user);
        wpmu_delete_user($user);

        $reset_url = STM_LMS_User::login_page_url() . '?user_token=' . $token;

        $subject = sprintf(esc_html__('Activate your account on site %s', 'masterstudy-lms-learning-management-system'), get_bloginfo('name'));
        $message = sprintf(
            esc_html__(
                'Please activate your account via this link - %s',
                'masterstudy-lms-learning-management-system'
            ),
            $reset_url
        );

        STM_LMS_Helpers::send_email(
            $user_email,
            $subject,
            $message,
            'stm_lms_account_premoderation',
            compact('reset_url')
        );

    }

    static function verify_user()
    {
        $token = sanitize_text_field($_GET['user_token']);

        $data = get_transient($token);

        if (!empty($data)) {
            extract($data);

            /**
             * @var $user_login
             * @var $user_password
             * @var $user_email
             */

            $user = wp_create_user($user_login, $user_password, $user_email);

            if (!is_wp_error($user)) self::_register_user($user, $data, $user_email);

            do_action('stm_lms_after_user_register', $user, $data);
        }

        wp_redirect(STM_LMS_User::login_page_url());
    }

    static function _register_user($user, $data, $user_email)
    {
        wp_signon($data, is_ssl());

        /*If everything is right, check for Instructor application*/
        STM_LMS_Instructor::become_instructor($data, $user);

        do_action('stm_lms_user_registered', $user, $data);


        $blog_name = get_bloginfo('name');
        $subject = esc_html__('You successfully register on site.', 'masterstudy-lms-learning-management-system');
        $message = sprintf(
            esc_html__(
                'Now you active user on site - %s. Add information and start chatting with other users - free and fast.',
                'masterstudy-lms-learning-management-system'
            ), $blog_name
        );

        STM_LMS_Helpers::send_email(
            $user_email,
            $subject,
            $message,
            'stm_lms_user_registered_on_site',
            compact('blog_name')
        );

        wp_new_user_notification($user);
    }

    static function stm_lms_set_user_role($user, $data)
    {
        if (!empty($data['become_instructor']) && $data['become_instructor']) {

            $disable_instructor_premoderation = STM_LMS_Options::get_option('disable_instructor_premoderation', false);
            if ($disable_instructor_premoderation) {
                $user_id = wp_update_user(array(
                    'ID' => $user,
                    'role' => 'stm_lms_instructor'
                ));
            }
        }

    }

    public static function get_current_user($id = '', $get_role = false, $get_meta = false, $no_avatar = false, $avatar_size = 215)
    {
        $user = array(
            'id' => 0
        );

        $current_user = (!empty($id)) ? get_userdata($id) : wp_get_current_user();

        $avatar_url = '';

        if (!empty($current_user->ID) and 0 != $current_user->ID) {

            if (!$no_avatar) {
                /*Get Meta*/
                $stm_lms_user_avatar = get_user_meta($current_user->ID, 'stm_lms_user_avatar', true);
                if (!empty($stm_lms_user_avatar)) {
                    $avatar = "<img src='{$stm_lms_user_avatar}' class='avatar photo' width='{$avatar_size}' />";
                    $avatar_url = $stm_lms_user_avatar;
                } else {
                    $avatar = get_avatar($current_user->ID, $avatar_size);

                    preg_match('/(src=["\'](.*?)["\'])/', $avatar, $match);
                    $split = preg_split('/["\']/', $match[0]);
                    if (is_array($split) and !empty($split[1])) $avatar_url = $split[1];
                }

            } else {
                $avatar = '';
            };

            $user = array(
                'id' => $current_user->ID,
                'login' => STM_LMS_User::display_name($current_user),
                'avatar' => $avatar,
                'avatar_url' => $avatar_url,
                'email' => $current_user->data->user_email,
                'url' => STM_LMS_User::user_public_page_url($current_user->ID)
            );

            if ($get_role) {
                $user_meta = get_userdata($current_user->ID);
                $user['roles'] = $user_meta->roles;
            }

            if ($get_meta) {
                $fields = STM_LMS_User::extra_fields();
                $fields = array_merge($fields, STM_LMS_User::additional_fields());
                $user['meta'] = array();
                foreach ($fields as $field_key => $field) {
                    $meta = get_user_meta($current_user->ID, $field_key, true);
                    $user['meta'][$field_key] = (!empty($meta)) ? $meta : '';
                }
            }

        }

        return apply_filters('stm_lms_current_user_data', $user);
    }

    public static function display_name($user)
    {
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        if (!empty($first_name) and !empty($last_name)) $first_name .= ' ' . $last_name;
        if (empty($first_name) and !empty($user->data->display_name)) $first_name = $user->data->display_name;
        $login = (!empty($first_name)) ? $first_name : $user->data->user_login;
        return $login;
    }

    public static function js_redirect($page)
    {
        ?>
        <script type="text/javascript">
            window.location = '<?php echo esc_url($page); ?>';
        </script>
    <?php }

    static function _get_user_courses($offset)
    {

        $user = STM_LMS_User::get_current_user();
        if (empty($user['id'])) die;
        $user_id = $user['id'];

        $r = array(
            'posts' => array(),
            'total' => false
        );

        $pp = get_option('posts_per_page');
        $offset = $offset * $pp;

        $r['offset'] = $offset;

        $total = 0;
        $all_courses = stm_lms_get_user_courses($user_id, '', '', array());
        foreach ($all_courses as $course_user) {
            if (get_post_type($course_user['course_id']) !== 'stm-courses') {
                stm_lms_get_delete_courses($course_user['course_id']);
                continue;
            }

            $total++;
        }
        $courses = stm_lms_get_user_courses($user_id, $pp, $offset, array('course_id', 'current_lesson_id', 'progress_percent', 'start_time'));

        $r['total_posts'] = $total;
        $r['total'] = $total <= $offset + $pp;
        $r['pages'] = ceil($total / $pp);
        if (!empty($courses)) {
            foreach ($courses as $course) {
                $id = $course['course_id'];

                if (get_post_type($id) !== 'stm-courses') {
                    stm_lms_get_delete_courses($id);
                    continue;
                }
                if (!get_post_status($id)) continue;

                $price = get_post_meta($id, 'price', true);
                $sale_price = STM_LMS_Course::get_sale_price($id);

                if (empty($price) and !empty($sale_price)) {
                    $price = $sale_price;
                    $sale_price = '';
                }

                $post_status = STM_LMS_Course::get_post_status($id);

                $image = (function_exists('stm_get_VC_img')) ? stm_get_VC_img(get_post_thumbnail_id($id), '272x161') : get_the_post_thumbnail($id, 'img-300-225');

                $course['progress_percent'] = ($course['progress_percent'] > 100) ? 100 : $course['progress_percent'];

                $current_lesson = (!empty($course['current_lesson_id'])) ? $course['current_lesson_id'] : STM_LMS_Lesson::get_first_lesson($id);

                ob_start();
                STM_LMS_Templates::show_lms_template('global/expired_course', array('course_id' => $id, 'expired_popup' => false));
                $expiration = ob_get_clean();

                $post = array(
                    'id' => $id,
                    'url' => get_the_permalink($id),
                    'image_id' => get_post_thumbnail_id($id),
                    'title' => get_the_title($id),
                    'link' => get_the_permalink($id),
                    'image' => $image,
                    'terms' => stm_lms_get_terms_array($id, 'stm_lms_course_taxonomy', false, true),
                    'terms_list' => stm_lms_get_terms_array($id, 'stm_lms_course_taxonomy', 'name'),
                    'views' => STM_LMS_Course::get_course_views($id),
                    'price' => STM_LMS_Helpers::display_price($price),
                    'sale_price' => STM_LMS_Helpers::display_price($sale_price),
                    'post_status' => $post_status,
                    'progress' => $course['progress_percent'],
                    'progress_label' => sprintf(esc_html__('%s%% Complete', 'masterstudy-lms-learning-management-system'), $course['progress_percent']),
                    'current_lesson_id' => STM_LMS_Lesson::get_lesson_url($id, $current_lesson),
                    'course_id' => $id,
                    'lesson_id' => $current_lesson,
                    'start_time' => sprintf(esc_html__('Started %s', 'masterstudy-lms-learning-management-system'), date_i18n(get_option('date_format'), $course['start_time'])),
                    'duration' => get_post_meta($id, 'duration_info', true),
                    'expiration' => $expiration,
                    'is_expired' => STM_LMS_Course::is_course_time_expired(get_current_user_id(), $id)
                );

                $r['posts'][] = $post;
            }
        }

        return $r;

    }

    public static function get_user_courses()
    {

        check_ajax_referer('stm_lms_get_user_courses', 'nonce');

        $offset = (!empty($_GET['offset'])) ? intval($_GET['offset']) : 0;

        $r = self::_get_user_courses($offset);

        wp_send_json(apply_filters('stm_lms_get_user_courses_filter', $r));
    }

    public static function get_user_quizzes()
    {

        check_ajax_referer('stm_lms_get_user_quizzes', 'nonce');

        $user = STM_LMS_User::get_current_user();
        if (empty($user['id'])) die;
        $user_id = $user['id'];

        $r = array(
            'posts' => array(),
            'total' => false
        );

        $pp = get_option('posts_per_page');
        $offset = (!empty($_GET['offset'])) ? intval($_GET['offset']) : 0;

        $offset = $offset * $pp;

        $quizzes = stm_lms_get_user_all_quizzes($user_id, $pp, $offset, array('course_id', 'quiz_id', 'progress', 'status'));

        $total = STM_LMS_Helpers::simplify_db_array(stm_lms_get_user_all_quizzes($user_id, '', '', array('course_id'), true));
        $total = $total['COUNT(*)'];

        $r['total'] = $total <= $offset + $pp;


        if (!empty($quizzes)) {
            foreach ($quizzes as $quiz) {
                $post_id = $quiz['course_id'];
                $item_id = $quiz['quiz_id'];
                $status_label = ($quiz['status'] == 'passed') ? esc_html__('Passed', 'masterstudy-lms-learning-management-system') : esc_html__('Failed', 'masterstudy-lms-learning-management-system');
                $course_title = (!empty(get_post_status($post_id))) ? get_the_title($post_id) : esc_html__('Course Deleted', 'masterstudy-lms-learning-management-system');
                $r['posts'][] = array_merge($quiz, array(
                    'course_title' => $course_title,
                    'course_url' => get_the_permalink($post_id),
                    'title' => get_the_title($item_id),
                    'url' => STM_LMS_Lesson::get_lesson_url($post_id, $item_id),
                    'status_label' => $status_label
                ));
            }
        }

        wp_send_json($r);
    }

    public static function get_user_meta($user_id, $key)
    {
        return get_user_meta($user_id, $key, true);
    }

    public static function has_course_access($course_id, $item_id = '', $add = true)
    {

        $user = STM_LMS_User::get_current_user();
        if (empty($user['id'])) return apply_filters('stm_lms_has_course_access', false, $course_id, $item_id);
        $user_id = $user['id'];

        /*If course Author*/
        $author_id = get_post_field('post_author', $course_id);
        if ($author_id == $user_id) {
            STM_LMS_Course::add_user_course($course_id, $user_id, STM_LMS_Course::item_url($course_id, ''), 0);
            return true;
        }

        if (STM_LMS_Cart::woocommerce_checkout_enabled()) {
            wc_customer_bought_product($user['email'], $user_id, $course_id);
        }

        $course = stm_lms_get_user_course($user_id, $course_id, array('user_course_id'));

        if (!count($course)) {
            /*If course is free*/
            $course_price = STM_LMS_Course::get_course_price($course_id);
            $not_salebale = get_post_meta($course_id, 'not_single_sale', true);

            if (empty($course_price) && !$not_salebale && $add) {
                STM_LMS_Course::add_user_course($course_id, $user_id, STM_LMS_Course::item_url($course_id, ''), 0);
                STM_LMS_Course::add_student($course_id);
                return true;
            }

        } else {
            /*Check for expiration*/
            $course_expired = STM_LMS_Course::is_course_time_expired($user_id, $course_id);
            if($course_expired) {
                return apply_filters('stm_lms_has_course_access', 0, $course_id, $item_id);
            }
        }

        return apply_filters('stm_lms_has_course_access', count($course), $course_id, $item_id);
    }

    public static function get_wishlist($user_id = 0)
    {

        $wishlist = array();

        if (!empty($user_id)) {
            $wishlist = get_user_meta($user_id, 'stm_lms_wishlist', true);
            if (empty($wishlist)) $wishlist = array();
        } else {
            if (!is_user_logged_in()) {
                $wishlist = (!empty($_COOKIE['stm_lms_wishlist'])) ? $_COOKIE['stm_lms_wishlist'] : array();
                if (!empty($wishlist)) {
                    $wishlist = array_filter(array_unique(explode(',', $wishlist)));
                }
                return $wishlist;
            }
        }

        return $wishlist;
    }

    public static function update_wishlist($user_id, $wishlist)
    {
        return update_user_meta($user_id, 'stm_lms_wishlist', array_unique(array_filter($wishlist)));
    }

    public static function wishlist()
    {

        check_ajax_referer('stm_lms_wishlist', 'nonce');

        if (empty($_GET['post_id'])) die;

        $user = STM_LMS_User::get_current_user();
        if (empty($user['id'])) die;
        $user_id = $user['id'];

        $r = array(
            'icon' => 'far fa-heart',
            'text' => esc_html__('Add to Wishlist', 'masterstudy-lms-learning-management-system')
        );

        $post_id = intval($_GET['post_id']);

        $wishlist = STM_LMS_User::get_wishlist($user_id);

        /*Add to wishlist*/
        if (!in_array($post_id, $wishlist)) {
            $wishlist[] = $post_id;
            $r = array(
                'icon' => 'fa fa-heart',
                'text' => esc_html__('Wishlisted', 'masterstudy-lms-learning-management-system')
            );
        } else {
            /*Remove*/
            $index = array_search($post_id, $wishlist);
            unset($wishlist[$index]);
        }

        STM_LMS_User::update_wishlist($user_id, $wishlist);

        wp_send_json($r);

    }

    public static function is_wishlisted($course_id, $user_id = '')
    {
        if (is_user_logged_in() || !empty($user_id)) {
            if (empty($user_id)) {
                $user = STM_LMS_User::get_current_user();
                $user_id = $user['id'];
            }
            $wishlist = STM_LMS_User::get_wishlist($user_id);
        } else {
            if (empty($_COOKIE['stm_lms_wishlist'])) return false;
            $wishlist = explode(',', sanitize_text_field($_COOKIE['stm_lms_wishlist']));
        }

        return in_array($course_id, $wishlist);
    }

    public static function user_logged_in($user_name, $user)
    {
        $user_id = $user->ID;
        STM_LMS_User::move_wishlist_to_user($user_id);
    }

    public static function move_wishlist_to_user($user_id)
    {
        if (empty($_COOKIE['stm_lms_wishlist'])) return false;
        $wishlist = explode(',', sanitize_text_field($_COOKIE['stm_lms_wishlist']));
        STM_LMS_User::update_wishlist($user_id, array_merge(STM_LMS_User::get_wishlist($user_id), $wishlist));
    }

    public static function wishlist_url($user_id = '')
    {
        return home_url('/') . STM_LMS_WP_Router::route_urls('wishlist');
    }

    public static function extra_fields()
    {
        $extra_fields = array(
            'facebook' => array(
                'label' => esc_html__('Facebook', 'masterstudy-lms-learning-management-system'),
                'icon' => 'facebook-f',
            ),
            'twitter' => array(
                'label' => esc_html__('Twitter', 'masterstudy-lms-learning-management-system'),
                'icon' => 'twitter',
            ),
            'instagram' => array(
                'label' => esc_html__('Instagram', 'masterstudy-lms-learning-management-system'),
                'icon' => 'instagram',
            ),
            'google-plus' => array(
                'label' => esc_html__('Google Plus', 'masterstudy-lms-learning-management-system'),
                'icon' => 'google-plus-g',
            ),
            'position' => array(
                'label' => esc_html__('Position', 'masterstudy-lms-learning-management-system'),
            ),
        );

        return apply_filters('stm_lms_extra_user_fields', $extra_fields);

    }

    public static function additional_fields()
    {
        return array(
            'description' => array(
                'label' => esc_html__('Bio', 'masterstudy-lms-learning-management-system'),
            ),
            'first_name' => array(
                'label' => esc_html__('Name', 'masterstudy-lms-learning-management-system'),
            ),
            'last_name' => array(
                'label' => esc_html__('Name', 'masterstudy-lms-learning-management-system'),
            ),

        );
    }

    public static function rating_fields()
    {
        return array(
            'sum_rating' => array(
                'label' => esc_html__('Summary rating', 'masterstudy-lms-learning-management-system'),
            ),
            'total_reviews' => array(
                'label' => esc_html__('Total Reviews', 'masterstudy-lms-learning-management-system'),
            ),
        );
    }

    public static function extra_fields_display($user)
    { ?>
        <h3><?php esc_html_e("Extra profile information", "stm-lms"); ?></h3>


        <table class="form-table">
            <?php $fields = STM_LMS_User::extra_fields();
            foreach ($fields as $field_key => $field):?>
                <tr>
                    <th>
                        <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_attr($field['label']); ?></label>
                    </th>
                    <td>
                        <input type="text" name="<?php echo esc_attr($field_key); ?>"
                               id="<?php echo esc_attr($field_key); ?>"
                               value="<?php echo esc_attr(get_the_author_meta($field_key, $user->ID)); ?>"
                               class="regular-text"/><br/>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <?php if (current_user_can('manage_options')): ?>
        <h3><?php esc_html_e("Rating information", "stm-lms"); ?></h3>

        <table class="form-table">
            <?php $fields = STM_LMS_User::rating_fields();
            foreach ($fields as $field_key => $field):?>
                <tr>
                    <th>
                        <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_attr($field['label']); ?></label>
                    </th>
                    <td>
                        <input type="text" name="<?php echo esc_attr($field_key); ?>"
                               id="<?php echo esc_attr($field_key); ?>"
                               value="<?php echo esc_attr(get_the_author_meta($field_key, $user->ID)); ?>"
                               class="regular-text"/><br/>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <!-- Zoom.us        -->
        <?php if (class_exists('StmZoom') || class_exists('Video_Conferencing_With_Zoom')): ?>
            <?php

            if (class_exists('StmZoom')) {
                $zoom_users = StmZoom::stm_zoom_get_users();
            } else {
                $zoom_users_list = video_conferencing_zoom_api_get_user_transients();
                $zoom_users = array();
                foreach ($zoom_users_list as $zoom_user) {
                    $zoom_users[] = array(
                        'id' => $zoom_user->id,
                        'first_name' => $zoom_user->first_name,
                        'email' => $zoom_user->email,
                    );
                }
            }
            $user_host = get_the_author_meta('stm_lms_zoom_host', $user->ID);
            ?>
            <h3><?php esc_html_e("Zoom.us settings", "stm-lms"); ?></h3>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="stm_lms_zoom_host"><?php esc_html_e("Meeting Host", "stm-lms"); ?></label>
                    </th>
                    <td>
                        <select id="stm_lms_zoom_host" name="stm_lms_zoom_host">
                            <option value=""><?php esc_html_e("Select host", "stm-lms"); ?></option>
                            <?php foreach ($zoom_users as $zoom_user): ?>
                                <option value="<?php echo $zoom_user['id']; ?>" <?php !empty($user_host) ? selected($user_host, $zoom_user['id']) : false; ?> ><?php echo $zoom_user['first_name'] . ' ( ' . $zoom_user['email'] . ' )'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        <!-- end Zoom.us        -->
        <!--Google Classrooms Auditory-->
        <?php if (class_exists('STM_LMS_Google_Classroom')):
            $g_c_key = 'google_classroom_auditory';
            $auditories = STM_LMS_Helpers::get_posts('stm-auditory');
            $selected_auditory = get_the_author_meta($g_c_key, $user->ID);
            ?>
            <table class="form-table">
                <tr>
                    <th>
                        <label for="<?php echo esc_attr($g_c_key); ?>">
                            <?php esc_html_e('Google Classroom auditory', 'masterstudy-lms-learning-management-system') ?>
                        </label>
                    </th>
                    <td>
                        <select name="<?php echo esc_attr($g_c_key); ?>" id="<?php echo esc_attr($g_c_key); ?>">
                            <option value=""><?php esc_html_e('Select auditory', 'masterstudy-lms-learning-management-system'); ?></option>
                            <?php foreach ($auditories as $auditory_value => $auditory_name): ?>
                                <option value="<?php echo esc_attr($auditory_value) ?>"
                                    <?php echo esc_attr(selected($selected_auditory, $auditory_value)); ?>>
                                    <?php echo esc_attr($auditory_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        <?php endif; ?>


    <?php endif;
    }

    public static function save_extra_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        $fields = STM_LMS_User::extra_fields();
        foreach ($fields as $field_key => $field) {
            update_user_meta($user_id, $field_key, sanitize_text_field($_POST[$field_key]));
        }

        if (current_user_can('manage_options')) {
            $fields = STM_LMS_User::rating_fields();
            foreach ($fields as $field_key => $field) {
                update_user_meta($user_id, $field_key, sanitize_text_field($_POST[$field_key]));
            }

            if (!empty($_POST['google_classroom_auditory'])) {
                update_user_meta($user_id, 'google_classroom_auditory', intval($_POST['google_classroom_auditory']));
            }

            if (isset($_POST['stm_lms_zoom_host'])) {
                update_user_meta($user_id, 'stm_lms_zoom_host', sanitize_text_field($_POST['stm_lms_zoom_host']));
            }
        }
    }

    public static function save_user_info()
    {

        $user = STM_LMS_User::get_current_user();
        if (empty($user['id'])) die;
        $user_id = $user['id'];

        $new_pass = (isset($_GET['new_pass'])) ? sanitize_text_field($_GET['new_pass']) : '';
        $new_pass_re = (isset($_GET['new_pass_re'])) ? sanitize_text_field($_GET['new_pass_re']) : '';

        if (!empty($new_pass) and !empty($new_pass_re)) {
            if ($new_pass !== $new_pass_re) {
                wp_send_json(array(
                    'status' => 'error',
                    'message' => esc_html__('New password do not match', 'masterstudy-lms-learning-management-system')
                ));
            } else {

                $subject = esc_html__('Password change', 'masterstudy-lms-learning-management-system');
                $message = esc_html__('Password changed successfully.', 'masterstudy-lms-learning-management-system');
                STM_LMS_Helpers::send_email(
                    $user['email'],
                    $subject,
                    $message,
                    'stm_lms_password_change'
                );

                wp_set_password($new_pass, $user_id);
                wp_send_json(array(
                    'relogin' => STM_LMS_User::login_page_url(),
                    'status' => 'success',
                    'message' => esc_html__('Password Changed. Re-login now', 'masterstudy-lms-learning-management-system')
                ));
            }
        }

        $fields = STM_LMS_User::extra_fields();
        $fields = array_merge($fields, STM_LMS_User::additional_fields());

        $data = array();

        foreach ($fields as $field_name => $field) {
            if (isset($_GET[$field_name])) {
                $new_value = sanitize_text_field($_GET[$field_name]);
                update_user_meta($user_id, $field_name, $new_value);
                $data[$field_name] = $new_value;
            }
        }

        /*change nicename*/
        $nicename = '';
        if (!empty($_GET['first_name'])) $nicename = sanitize_text_field($_GET['first_name']);
        if (!empty($_GET['last_name'])) $nicename = (!empty($nicename)) ? $nicename . ' ' . sanitize_text_field($_GET['last_name']) : sanitize_text_field($_GET['last_name']);
        if (!empty($nicename)) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $nicename
            ));
        }

        $r = array(
            'data' => $data,
            'status' => 'success',
            'message' => esc_html__('Successfully saved', 'masterstudy-lms-learning-management-system')
        );

        wp_send_json($r);
    }

    public static function stm_lms_logout()
    {
        wp_destroy_current_session();
        wp_clear_auth_cookie();
        wp_set_current_user(0);

        wp_send_json(STM_LMS_User::login_page_url());
    }

    public static function apply_for_instructor()
    {

        check_ajax_referer('stm_lms_become_instructor', 'nonce');

        $user = STM_LMS_User::get_current_user();
        if (empty($user['id'])) die;
        $user_id = $user['id'];

        $r = array(
            'status' => 'success',
            'message' => esc_html__('Your Application is under submission.', 'masterstudy-lms-learning-management-system')
        );

        $data = array(
            'become_instructor' => true
        );

        $request_body = file_get_contents('php://input');
        $get = json_decode($request_body, true);

        $data['degree'] = (!empty($get['degree'])) ? sanitize_text_field($get['degree']) : '';
        $data['expertize'] = (!empty($get['expertize'])) ? sanitize_text_field($get['expertize']) : '';

        if (empty($data['degree']) or empty($data['expertize'])) {
            $r['status'] = 'error';
            $r['message'] = esc_html__('Please fill all fields', 'masterstudy-lms-learning-management-system');
        }

        STM_LMS_Instructor::become_instructor($data, $user_id);

        wp_send_json($r);
    }

    public static function enterprise()
    {

        check_ajax_referer('stm_lms_enterprise', 'nonce');

        $r = array(
            'status' => 'success',
            'message' => esc_html__('Message sent.', 'masterstudy-lms-learning-management-system')
        );

        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);

        $fields = array('name', 'email', 'text');
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $r = array(
                    'status' => 'error',
                    'message' => esc_html__('Please fill al fields', 'masterstudy-lms-learning-management-system')
                );
            }
        }

        if ($r['status'] !== 'error') {

            $name = $data['name'];
            $email = $data['email'];
            $text = $data['text'];

            $subject = esc_html__('Enterprise Request', 'masterstudy-lms-learning-management-system');
            $message = sprintf(esc_html__('Name - %s; Email - %s; Message - %s', 'masterstudy-lms-learning-management-system'),
                $name,
                $email,
                $text
            );

            STM_LMS_Helpers::send_email(
                '',
                $subject,
                $message,
                'stm_lms_enterprise',
                compact('name', 'email', 'text')
            );
        }


        wp_send_json($r);
    }

    public static function stm_lms_lost_password()
    {

        check_ajax_referer('stm_lms_lost_password', 'nonce');

        $r = array(
            'status' => 'success',
            'message' => esc_html__('Further Instructions sent on E-mail.', 'masterstudy-lms-learning-management-system')
        );

        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);

        $fields = array('user_login');

        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $r = array(
                    'status' => 'error',
                    'message' => esc_html__('Please fill al fields', 'masterstudy-lms-learning-management-system')
                );
            }
        }

        if ($r['status'] !== 'error') {

            $user_login = $data['user_login'];

            $errors = new WP_Error();

            if (empty($user_login) || !is_string($user_login)) {
                $errors->add('empty_username', __('<strong>ERROR</strong>: Enter a username or email address.'));
            } elseif (strpos($user_login, '@')) {
                $user_data = get_user_by('email', trim(wp_unslash($user_login)));
                if (empty($user_data)) {
                    $errors->add('invalid_email', __('<strong>ERROR</strong>: There is no account with that username or email address.'));
                }
            } else {
                $login = trim($user_login);
                $user_data = get_user_by('login', $login);
            }

            if ($errors->has_errors()) {
                wp_send_json(array(
                    'status' => 'error',
                    'message' => $errors->get_error_message()
                ));
            }

            if (!($user_data)) {
                wp_send_json(array(
                    'status' => 'error',
                    'message' => __('ERROR: There is no account with that username or email address.')
                ));
            }

            // Redefining user_login ensures we return the right case in the email.
            $user_login = $user_data->user_login;
            $user_email = $user_data->user_email;
            $key = get_password_reset_key($user_data);

            if (is_wp_error($key)) {
                $r = array(
                    'status' => 'error',
                    'message' => __('ERROR: There is no account with that username or email address.')
                );
            }

            if (is_multisite()) {
                $site_name = get_network()->site_name;
            } else {
                $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
            }

            $token = $user_data->ID . '*' . bin2hex(openssl_random_pseudo_bytes(16));
            update_user_meta($user_data->ID, 'restore_password_token', $token);
            $reset_url = add_query_arg('restore_password', $token, STM_LMS_User::login_page_url());

            $message = __('Someone has requested a password reset for the following account:') . "\r\n\r\n";
            /* translators: %s: site name */
            $message .= sprintf(__('Site Name: %s'), $site_name) . "\r\n\r\n";
            /* translators: %s: user login */
            $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
            $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
            $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
            $message .= '<' . $reset_url . ">\r\n";

            /* translators: Password reset notification email subject. %s: Site title */
            $title = sprintf(__('[%s] Password Reset'), $site_name);

            $title = apply_filters('retrieve_password_title', $title, $user_login, $user_data);
            $message = apply_filters('retrieve_password_message', stripslashes($message), $key, $user_login, $user_data);

            if ($message && !wp_mail($user_email, wp_specialchars_decode($title), $message)) {
                $r = array(
                    'status' => 'error',
                    'message' => esc_html__('Cant send E-mail.', 'masterstudy-lms-learning-management-system')
                );
            }

        }

        wp_send_json($r);
    }

    public static function stm_lms_change_avatar($user = array(), $files = array(), $return = false)
    {

        //check_ajax_referer('stm_lms_change_avatar', 'nonce');

        if (empty($files)) $files = $_FILES;

        $is_valid_image = Validation::is_valid($files, array(
            'file' => 'required_file|extension,png;jpg;jpeg'
        ));

        if ($is_valid_image !== true) {

            $res = array(
                'error' => true,
                'message' => $is_valid_image[0]
            );

            if ($return) {
                return $res;
            } else {
                wp_send_json($res);
            }
        }

        if (empty($user)) $user = STM_LMS_User::get_current_user();

        if (empty($user['id'])) die;

        if (!apply_filters('stm_lms_update_user_avatar', true)) {
            $res = array(
                'error' => true,
                'message' => esc_html__('Site is on demo mode.', 'masterstudy-lms-learning-management-system')
            );
            if ($return) {
                return $res;
            } else {
                wp_send_json($res);
            }
        }

        /*Create directory*/
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $dir = wp_upload_dir();
        $base_dir = $dir['basedir'] . '/stm_lms_avatars';
        $base_url = $dir['baseurl'] . '/stm_lms_avatars';

        if (!is_dir($base_dir)) wp_mkdir_p($base_dir);

        $file_upload = $files['file']['tmp_name'];
        $file_extension = pathinfo($files['file']['name'], PATHINFO_EXTENSION);
        $file_name = "stm_lms_avatar" . $user['id'] . '.' . $file_extension;
        $file = "{$base_dir}/{$file_name}";

        if (file_exists($file)) unlink($file);

        move_uploaded_file($file_upload, $file);

        $image = wp_get_image_editor($file);
        if (!is_wp_error($image)) {
            $image->resize(512, 512, true);
            $image->save($file);
        }

        update_user_meta($user['id'], 'stm_lms_user_avatar', "{$base_url}/{$file_name}?v=" . time());

        $res = array(
            'file' => "{$base_url}/{$file_name}?v=" . time(),
        );

        if (!$return) wp_send_json($res);

        return $res;

    }

    public static function stm_lms_delete_avatar()
    {

        check_ajax_referer('stm_lms_delete_avatar', 'nonce');

        $user = STM_LMS_User::get_current_user();
        if (empty($user['id'])) die;

        update_user_meta($user['id'], 'stm_lms_user_avatar', "");

        wp_send_json(
            array(
                'file' => $avatar = get_avatar($user['id'], '215')
            )
        );

    }

    public static function check_restore_token($token)
    {

        $token_parts = explode('*', $token);
        if (!is_array($token_parts) and count($token_parts) !== 2) {
            return false;
        }

        $user_id = $token_parts[0];
        $original_token = get_user_meta($user_id, 'restore_password_token', true);

        return ($original_token === $token) ? intval($user_id) : false;
    }

    public static function stm_lms_restore_password()
    {
        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);

        $token = sanitize_text_field($data['token']);
        $password = sanitize_text_field($data['password']);

        $user_id = self::check_restore_token($token);

        if (empty($user_id)) {
            wp_send_json(array(
                'status' => 'error',
                'message' => esc_html__('Your token expired, try again', 'masterstudy-lms-learning-management-system')
            ));
        }

        wp_set_password($password, $user_id);
        delete_user_meta($user_id, 'restore_password_token');

        wp_send_json(array(
            'status' => 'success',
            'message' => esc_html__('Password changed.', 'masterstudy-lms-learning-management-system')
        ));


    }

}