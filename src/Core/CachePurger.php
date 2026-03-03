<?php
declare( strict_types=1 );

namespace CodeUnloader\Core;

/**
 * CachePurger
 *
 * After a rule is created or deleted, the pages that rule matches should have
 * their cache cleared so the change takes effect without a manual flush.
 * Supports WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache,
 * WP Fastest Cache, Autoptimize, and a generic filter hook for others.
 */
class CachePurger {

	/**
	 * Purge cache for a specific URL (exact match rules) or sitewide
	 * (wildcard/regex rules where we can't know all affected URLs cheaply).
	 *
	 * @param string $url_pattern  The stored url_pattern value from the rule.
	 * @param string $match_type   'exact', 'wildcard', or 'regex'.
	 */
	public static function purge_for_rule( string $url_pattern, string $match_type ): void {
		if ( 'exact' === $match_type ) {
			self::purge_url( $url_pattern );
		} else {
			// Wildcard/regex can affect many URLs — purge everything.
			self::purge_all();
		}

		do_action( 'code_unloader_after_cache_purge', $url_pattern, $match_type );
	}

	// -------------------------------------------------------------------------
	// Single-URL purge
	// -------------------------------------------------------------------------

	public static function purge_url( string $url ): void {
		// WP Rocket
		if ( function_exists( 'rocket_clean_post' ) ) {
			$post_id = url_to_postid( $url );
			if ( $post_id ) {
				rocket_clean_post( $post_id );
			}
		}
		if ( function_exists( 'rocket_clean_files' ) ) {
			rocket_clean_files( [ $url ] );
		}

		// LiteSpeed Cache
		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			do_action( 'litespeed_purge_url', $url );
		}

		// WP Super Cache
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( $url );
		}

		// WP Fastest Cache
		if ( class_exists( '\WpFastestCache' ) && method_exists( '\WpFastestCache', 'deleteCache' ) ) {
			( new \WpFastestCache() )->deleteCache( true );
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_url' ) ) {
			w3tc_flush_url( $url );
		}

		// Autoptimize
		if ( class_exists( '\autoptimizeCache' ) ) {
			\autoptimizeCache::clearall();
		}

		// Breeze (Cloudways)
		if ( class_exists( '\Breeze_Admin' ) ) {
			do_action( 'breeze_clear_all_cache' );
		}

		// SG Optimizer (SiteGround)
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache( $url );
		}

		// Nginx Helper
		if ( class_exists( '\Nginx_Helper' ) ) {
			do_action( 'rt_nginx_helper_purge_url', $url );
		}

		// Cloudflare (if using the official plugin)
		if ( class_exists( '\CF\WordPress\Hooks' ) ) {
			do_action( 'cloudflare_purge_by_url', [ $url ] );
		}

		do_action( 'code_unloader_purge_url', $url );
	}

	// -------------------------------------------------------------------------
	// Full-site purge
	// -------------------------------------------------------------------------

	public static function purge_all(): void {
		// WP Rocket
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}

		// LiteSpeed Cache
		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// W3 Total Cache
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}

		// WP Super Cache
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}

		// WP Fastest Cache
		if ( class_exists( '\WpFastestCache' ) && method_exists( '\WpFastestCache', 'deleteCache' ) ) {
			( new \WpFastestCache() )->deleteCache( true );
		}

		// Autoptimize
		if ( class_exists( '\autoptimizeCache' ) ) {
			\autoptimizeCache::clearall();
		}

		// Breeze
		if ( class_exists( '\Breeze_Admin' ) ) {
			do_action( 'breeze_clear_all_cache' );
		}

		// SG Optimizer
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}

		// Nginx Helper
		if ( class_exists( '\Nginx_Helper' ) ) {
			global $nginx_helper;
			if ( $nginx_helper && method_exists( $nginx_helper, 'purge_entire_cache' ) ) {
				$nginx_helper->purge_entire_cache();
			}
		}

		// Cloudflare
		if ( class_exists( '\CF\WordPress\Hooks' ) ) {
			do_action( 'cloudflare_purge_everything' );
		}

		do_action( 'code_unloader_purge_all' );
	}
}
