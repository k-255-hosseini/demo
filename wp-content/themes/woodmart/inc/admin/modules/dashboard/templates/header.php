<?php

global $menu;
global $submenu;

$logo_url = WOODMART_ASSETS_IMAGES . '/wood-logo-dark.svg';

if ( woodmart_get_opt( 'white_label' ) ) {
	$image_data = woodmart_get_opt( 'white_label_dashboard_logo' );

	if ( ! empty( $image_data['url'] ) ) {
		$logo_url = wp_get_attachment_image_url( $image_data['id'], 'full' );
	}
}

?>
<div class="xts-header xts-theme-style">
	<div class="xts-row">
		<div class="xts-col-auto xts-logo-wrap">
			<?php if ( current_user_can( apply_filters( 'woodmart_dashboard_theme_links_access', 'administrator' ) ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=xts_dashboard' ) ); ?>"></a>
			<?php endif; ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" class="xts-logo" alt="<?php esc_attr_e( 'لوگو', 'woodmart' ); ?>">
			<div class="xts-version">
				<?php echo esc_html( 'v.' . woodmart_get_theme_info( 'Version' ) ); ?>
			</div>
		</div>
		<div class="xts-col">
			<?php
			new XTS\Admin\Modules\Dashboard\Menu(
				array(
					'items' => array(
						array(
							'link'       => array(
								'url' => admin_url( 'admin.php?page=xts_theme_settings' ),
							),
							'type'       => 'page',
							'slug'       => 'xts_theme_settings',
							'icon'       => 'theme-settings',
							'text'       => esc_html__( 'تنظیمات قالب', 'woodmart' ),
							'condition'  => current_user_can( apply_filters( 'woodmart_capability_menu_page', 'manage_options', 'xts_theme_settings' ) ),
							'child_menu' => array(
								'items' => array(
									array(
										'link' => array(
											'url' => admin_url( 'admin.php?page=xts_theme_settings_presets' ),
										),
										'type' => 'page',
										'slug' => 'xts_theme_settings_presets',
										'icon' => 'cog',
										'text' => esc_html__( 'پریست ها', 'woodmart' ),
									),
									array(
										'link' => array(
											'url' => admin_url( 'admin.php?page=xts_theme_settings_backup' ),
										),
										'type' => 'page',
										'slug' => 'xts_theme_settings_backup',
										'icon' => 'round-right',
										'text' => esc_html__( 'بکاپ', 'woodmart' ),
									),
								),
							),
						),
						array(
							'link'      => array(
								'url' => admin_url( 'admin.php?page=xts_prebuilt_websites' ),
							),
							'type'      => 'page',
							'slug'      => 'xts_prebuilt_websites',
							'icon'      => 'dummy-content',
							'condition' => woodmart_get_opt( 'dummy_import', '1' ) && current_user_can( apply_filters( 'woodmart_capability_menu_page', 'manage_options', 'xts_prebuilt_websites' ) ),
							'text'      => esc_html__( 'دموهای آماده', 'woodmart' ),
						),
						array(
							'link'      => array(
								'url' => admin_url( 'admin.php?page=xts_license' ),
							),
							'type'      => 'page',
							'slug'      => 'xts_license',
							'icon'      => 'key',
							'condition' => woodmart_get_opt( 'white_label_theme_license_tab', '1' ) && current_user_can( apply_filters( 'woodmart_capability_menu_page', 'manage_options', 'xts_license' ) ),
							'text'      => esc_html__( 'لایسنس', 'woodmart' ),
							'class'     => woodmart_is_license_activated() ? '' : 'xts-license-not-activated',
						),
						array(
							'link'       => array(
								'url' => admin_url( 'admin.php?page=xts_plugins' ),
							),
							'type'       => 'page',
							'slug'       => 'xts_plugins',
							'icon'       => 'tools',
							'text'       => esc_html__( 'ابزارها', 'woodmart' ),
							'condition'  => current_user_can( apply_filters( 'woodmart_capability_menu_page', 'manage_options', 'xts_plugins' ) ),
							'child_menu' => array(
								'items' => array(
									array(
										'link' => array(
											'url' => admin_url( 'admin.php?page=xts_plugins' ),
										),
										'type' => 'page',
										'slug' => 'xts_plugins',
										'icon' => 'puzzle',
										'text' => esc_html__( 'افزونه ها', 'woodmart' ),
									),
									array(
										'link' => array(
											'url' => admin_url( 'admin.php?page=xts_patcher' ),
										),
										'type' => 'page',
										'slug' => 'xts_patcher',
										'icon' => 'cog',
										'text' => esc_html__( 'پچر', 'woodmart' ),
									),
									array(
										'link' => array(
											'url' => admin_url( 'admin.php?page=xts_status' ),
										),
										'type' => 'page',
										'slug' => 'xts_status',
										'icon' => 'status',
										'text' => esc_html__( 'وضعیت', 'woodmart' ),
									),
									array(
										'link'      => array(
											'url' => admin_url( 'admin.php?page=xts_changelog' ),
										),
										'type'      => 'page',
										'slug'      => 'xts_changelog',
										'icon'      => 'file-text',
										'condition' => woodmart_get_opt( 'white_label_changelog_tab', '1' ),
										'text'      => esc_html__( 'تغییرات', 'woodmart' ),
									),
									array(
										'link'      => array(
											'url' => admin_url( 'admin.php?page=xts_wpb_css_generator' ),
										),
										'type'      => 'page',
										'slug'      => 'xts_wpb_css_generator',
										'icon'      => 'code',
										'condition' => 'wpb' === woodmart_get_current_page_builder(),
										'text'      => esc_html__( 'سازنده سی اس اس', 'woodmart' ),
									),
								),
							),
						),
					),
				)
			);
			?>
			<?php
			new XTS\Admin\Modules\Dashboard\Menu(
				array(
					'items' => array(
						array(
							'link'      => array(
								'url' => admin_url( 'admin.php?page=xts_header_builder' ),
							),
							'type'      => 'page',
							'slug'      => 'xts_header_builder',
							'icon'      => 'header-builder',
							'text'      => esc_html__( 'سربرگ ساز', 'woodmart' ),
							'condition' => current_user_can( apply_filters( 'woodmart_capability_menu_page', 'manage_options', 'xts_header_builder' ) ),
						),
						array(
							'link'      => array(
								'url' => admin_url( 'edit.php?post_type=woodmart_layout' ),
							),
							'type'      => 'post_type',
							'slug'      => 'woodmart_layout',
							'icon'      => 'layouts',
							'text'      => esc_html__( 'طرح ها', 'woodmart' ),
							'condition' => in_array( 'edit.php?post_type=woodmart_layout', array_column( $menu, 2 ) ),
						),
						array(
							'link'       => array(
								'url' => admin_url( 'edit-tags.php?taxonomy=woodmart_slider&post_type=woodmart_slide' ),
							),
							'type'       => 'post_type_taxonomy',
							'slug'       => 'woodmart_slide',
							'icon'       => 'slides',
							'text'       => esc_html__( 'اسلایدرها', 'woodmart' ),
							'condition'  => woodmart_get_opt( 'woodmart_slider', '1' ) && in_array( 'edit.php?post_type=woodmart_slide', array_column( $menu, 2 ) ),
							'child_menu' => array(
								'items' => array(
									array(
										'link'      => array(
											'url' => admin_url( 'edit.php?post_type=woodmart_slide' ),
										),
										'type'      => 'post_type',
										'slug'      => 'woodmart_slide',
										'condition' => woodmart_get_opt( 'woodmart_slider', '1' ) && isset( $submenu['edit.php?post_type=woodmart_slide'] ),
										'text'      => esc_html__( 'همه اسلایدها', 'woodmart' ),
									),
									array(
										'link'      => array(
											'url' => admin_url( 'post-new.php?post_type=woodmart_slide' ),
										),
										'type'      => 'post_type_new',
										'slug'      => 'woodmart_slide',
										'condition' => woodmart_get_opt( 'woodmart_slider', '1' ) && isset( $submenu['edit.php?post_type=woodmart_slide'] ),
										'text'      => esc_html__( 'اسلاید جدید', 'woodmart' ),
									),
								),
							),
						),
						array(
							'link'       => array(
								'url' => admin_url( 'edit.php?post_type=cms_block' ),
							),
							'type'       => 'post_type',
							'slug'       => 'cms_block',
							'icon'       => 'html-block',
							'condition'  => in_array( 'edit.php?post_type=cms_block', array_column( $menu, 2 ) ),
							'text'       => esc_html__( 'بلاک های HTML', 'woodmart' ),
							'child_menu' => array(
								'items' => array(
									array(
										'link'      => array(
											'url' => admin_url( 'edit-tags.php?taxonomy=cms_block_cat&post_type=cms_block' ),
										),
										'type'      => 'post_type_taxonomy',
										'slug'      => 'cms_block',
										'condition' => isset( $submenu['edit.php?post_type=cms_block'] ),
										'text'      => esc_html__( 'دسته بندی ها', 'woodmart' ),
									),
									array(
										'link'      => array(
											'url' => admin_url( 'post-new.php?post_type=cms_block' ),
										),
										'type'      => 'post_type_new',
										'slug'      => 'cms_block',
										'condition' => isset( $submenu['edit.php?post_type=cms_block'] ),
										'text'      => esc_html__( 'افزودن جدید', 'woodmart' ),
									),
								),
							),
						),
						array(
							'link'       => array(
								'url' => admin_url( 'edit.php?post_type=woodmart_sidebar' ),
							),
							'type'       => 'post_type',
							'slug'       => 'woodmart_sidebar',
							'icon'       => 'sidebars',
							'condition'  => in_array( 'edit.php?post_type=woodmart_sidebar', array_column( $menu, 2 ) ),
							'text'       => esc_html__( 'سایدبارها', 'woodmart' ),
							'child_menu' => array(
								'items' => array(
									array(
										'link'      => array(
											'url' => admin_url( 'post-new.php?post_type=woodmart_sidebar' ),
										),
										'type'      => 'post_type_new',
										'slug'      => 'woodmart_sidebar',
										'condition' => isset( $submenu['edit.php?post_type=woodmart_sidebar'] ),
										'text'      => esc_html__( 'افزودن', 'woodmart' ),
									),
								),
							),
						),
					),
				)
			);
			?>
		</div>
	</div>
</div>
