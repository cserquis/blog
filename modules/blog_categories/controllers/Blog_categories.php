<?php
class Blog_categories extends Trongate {

    private $default_limit = 20;
    private $per_page_options = array(10, 20, 50, 100); 

    function _get_category($categories_id) {
        $category_c = $this->model->get_one_where('id', $categories_id, 'blog_categories');
        if ($category_c == true){
            $category = $category_c->category_name;
        } else {
            $category = "";
        }        
        return $category;
    }

    function _get_blogs($blog_categories_id) {
        $blog_result = $this->model->get_many_where('blog_categories_id', $blog_categories_id, 'associated_blog_notices_and_blog_categories');
        if ($blog_result == false){
            $blogs_print = [];
        } else {
            $this->module('blog_notices');
            $blogs_print = [];
            foreach ($blog_result as $key => $value) {
              $blogs_print[$key] = $this->blog_notices->_get_notice($blog_result[$key]->blog_notices_id);             
            }
        }  
        
        return $blogs_print;
    }
    
    function _get_category_name($blog_cat_id) {
        $params['blog_category_id'] = $blog_cat_id;
        $sql = 'SELECT             
        category_name FROM blog_categories WHERE id = :blog_category_id ';
        
        $data = $this->model->query_bind($sql, $params, 'object');
        if(!empty($data)) {
            $category = $data[0]->category_name;    
        } else {
            $category = '';
        }
        
        return $category;
    }

    function create() {
        $this->module('trongate_security');
        $this->trongate_security->_make_sure_allowed();

        $update_id = segment(3);
        $submit = post('submit');

        if (($submit == '') && (is_numeric($update_id))) {
            $data = $this->_get_data_from_db($update_id);
        } else {
            $data = $this->_get_data_from_post();
        }

        if (is_numeric($update_id)) {
            $data['headline'] = 'Update Blog Category Record';
            $data['cancel_url'] = BASE_URL.'blog_categories/show/'.$update_id;
        } else {
            $data['headline'] = 'Create New Blog Category Record';
            $data['cancel_url'] = BASE_URL.'blog_categories/manage';
        }

        $data['form_location'] = BASE_URL.'blog_categories/submit/'.$update_id;
        $data['view_file'] = 'create';
        $this->template('admin', $data);
    }

    function manage() {
        $this->module('trongate_security');
        $this->trongate_security->_make_sure_allowed();

        if (segment(4) !== '') {
            $data['headline'] = 'Search Results';
            $searchphrase = trim($_GET['searchphrase']);
            $params['category_name'] = '%'.$searchphrase.'%';
            $sql = 'select * from blog_categories
            WHERE category_name LIKE :category_name
            ORDER BY id';
            $all_rows = $this->model->query_bind($sql, $params, 'object');
        } else {
            $data['headline'] = 'Manage Blog Categories';
            $all_rows = $this->model->get('id');
        }

        $pagination_data['total_rows'] = count($all_rows);
        $pagination_data['page_num_segment'] = 3;
        $pagination_data['limit'] = $this->_get_limit();
        $pagination_data['pagination_root'] = 'blog_categories/manage';
        $pagination_data['record_name_plural'] = 'blog categories';
        $pagination_data['include_showing_statement'] = true;
        $data['pagination_data'] = $pagination_data;

        $data['rows'] = $this->_reduce_rows($all_rows);
        $data['selected_per_page'] = $this->_get_selected_per_page();
        $data['per_page_options'] = $this->per_page_options;
        $data['view_module'] = 'blog_categories';
        $data['view_file'] = 'manage';
        $this->template('admin', $data);
    }

    function show() {
        $this->module('trongate_security');
        $token = $this->trongate_security->_make_sure_allowed();
        $update_id = segment(3);

        if ((!is_numeric($update_id)) && ($update_id != '')) {
            redirect('blog_categories/manage');
        }

        $data = $this->_get_data_from_db($update_id);
        $data['token'] = $token;

        if ($data == false) {
            redirect('blog_categories/manage');
        } else {
            $data['update_id'] = $update_id;
            $data['headline'] = 'Blog Category Information';
            $data['view_file'] = 'show';
            $this->template('admin', $data);
        }
    }
    
    function _reduce_rows($all_rows) {
        $rows = [];
        $start_index = $this->_get_offset();
        $limit = $this->_get_limit();
        $end_index = $start_index + $limit;

        $count = -1;
        foreach ($all_rows as $row) {
            $count++;
            if (($count>=$start_index) && ($count<$end_index)) {
                $row->blogs = $this->_get_blogs($row->id);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    function submit() {
        $this->module('trongate_security');
        $this->trongate_security->_make_sure_allowed();

        $submit = post('submit', true);

        if ($submit == 'Submit') {

            $this->validation_helper->set_rules('category_name', 'Category Name', 'required|min_length[2]|max_length[255]');

            $result = $this->validation_helper->run();

            if ($result == true) {

                $update_id = segment(3);
                $data = $this->_get_data_from_post();
                $data['url_string'] = strtolower(url_title($data['category_name']));

                if (is_numeric($update_id)) {
                    //update an existing record
                    $this->model->update($update_id, $data, 'blog_categories');
                    $flash_msg = 'The record was successfully updated';
                } else {
                    //insert the new record
                    $update_id = $this->model->insert($data, 'blog_categories');
                    $flash_msg = 'The record was successfully created';
                }

                set_flashdata($flash_msg);
                redirect('blog_categories/show/'.$update_id);

            } else {
                //form submission error
                $this->create();
            }

        }

    }

    function submit_delete() {
        $this->module('trongate_security');
        $this->trongate_security->_make_sure_allowed();

        $submit = post('submit');
        $params['update_id'] = segment(3);

        if (($submit == 'Yes - Delete Now') && (is_numeric($params['update_id']))) {
            //delete all of the comments associated with this record
            $sql = 'delete from trongate_comments where target_table = :module and update_id = :update_id';
            $params['module'] = 'blog_categories';
            $this->model->query_bind($sql, $params);

            unset($params['module']);
            //delete associated blogs
            $sql = 'delete from associated_blog_notices_and_blog_categories where blog_categories_id = :update_id';
            
            $this->model->query_bind($sql, $params);

            //delete the record
            $this->model->delete($params['update_id'], 'blog_categories');

            //set the flashdata
            $flash_msg = 'The record was successfully deleted';
            set_flashdata($flash_msg);

            //redirect to the manage page
            redirect('blog_categories/manage');
        }
    }

    function _get_limit() {
        if (isset($_SESSION['selected_per_page'])) {
            $limit = $this->per_page_options[$_SESSION['selected_per_page']];
        } else {
            $limit = $this->default_limit;
        }

        return $limit;
    }

    function _get_offset() {
        $page_num = segment(3);

        if (!is_numeric($page_num)) {
            $page_num = 0;
        }

        if ($page_num>1) {
            $offset = ($page_num-1)*$this->_get_limit();
        } else {
            $offset = 0;
        }

        return $offset;
    }

    function _get_selected_per_page() {
        if (!isset($_SESSION['selected_per_page'])) {
            $selected_per_page = $this->per_page_options[1];
        } else {
            $selected_per_page = $_SESSION['selected_per_page'];
        }

        return $selected_per_page;
    }

    function set_per_page($selected_index) {
        $this->module('trongate_security');
        $this->trongate_security->_make_sure_allowed();

        if (!is_numeric($selected_index)) {
            $selected_index = $this->per_page_options[1];
        }

        $_SESSION['selected_per_page'] = $selected_index;
        redirect('blog_categories/manage');
    }

    function _get_data_from_db($update_id) {
        $record_obj = $this->model->get_where($update_id, 'blog_categories');

        if ($record_obj == false) {
            $this->template('error_404');
            die();
        } else {
            $data = (array) $record_obj;
            return $data;        
        }
    }

    function _get_data_from_post() {
        $data['category_name'] = post('category_name', true);        
        return $data;
    }

}