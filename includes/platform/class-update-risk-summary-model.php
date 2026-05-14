<?php
/**
 * Deterministic update risk summary for future AI-assisted workflows.
 *
 * @package ZignitesSentinel
 */

namespace Zignites\Sentinel\Platform;

defined( 'ABSPATH' ) || exit;

class UpdateRiskSummaryModel {

	/**
	 * Build a deterministic update risk summary.
	 *
	 * @param array $targets       Update target rows.
	 * @param array $validation    Source/package validation payload.
	 * @param array $context       Update window or capture context.
	 * @param array $changelog_map Optional changelog/version context by target key.
	 * @return array
	 */
	public function build_summary( array $targets, array $validation = array(), array $context = array(), array $changelog_map = array() ) {
		$targets       = $this->normalize_targets( $targets );
		$type_counts   = $this->count_types( $targets );
		$version_risk  = $this->summarize_version_risk( $targets );
		$source_risk   = $this->summarize_source_risk( $validation );
		$changelog     = $this->summarize_changelog_context( $targets, $changelog_map );
		$risk_level    = $this->resolve_risk_level( $targets, $type_counts, $version_risk, $source_risk, $changelog );

		return array(
			'schema_version'         => '1.0',
			'generated_at'           => current_time( 'mysql', true ),
			'summary_type'           => 'deterministic_update_risk_summary',
			'risk_level'             => $risk_level,
			'title'                  => $this->build_title( $risk_level, $context ),
			'overview'               => $this->build_overview( $risk_level, $targets, $type_counts ),
			'target_count'           => count( $targets ),
			'type_counts'            => $type_counts,
			'targets'                => $targets,
			'version_risk'           => $version_risk,
			'source_risk'            => $source_risk,
			'changelog'              => $changelog,
			'findings'               => $this->build_findings( $type_counts, $version_risk, $source_risk, $changelog ),
			'next_steps'             => $this->build_next_steps( $risk_level, $source_risk, $changelog ),
			'auto_update_allowed'    => false,
			'auto_rollback_allowed'  => false,
			'ai_assistive_only'      => true,
			'deterministic_fallback' => true,
		);
	}

	/**
	 * Normalize update targets.
	 *
	 * @param array $targets Raw target rows.
	 * @return array
	 */
	protected function normalize_targets( array $targets ) {
		$normalized = array();

		foreach ( $targets as $target ) {
			if ( ! is_array( $target ) ) {
				continue;
			}

			$normalized[] = array(
				'key'             => isset( $target['key'] ) ? sanitize_text_field( (string) $target['key'] ) : '',
				'type'            => isset( $target['type'] ) ? sanitize_key( (string) $target['type'] ) : 'unknown',
				'slug'            => isset( $target['slug'] ) ? sanitize_text_field( (string) $target['slug'] ) : '',
				'label'           => isset( $target['label'] ) ? sanitize_text_field( (string) $target['label'] ) : '',
				'current_version' => isset( $target['current_version'] ) ? sanitize_text_field( (string) $target['current_version'] ) : '',
				'new_version'     => isset( $target['new_version'] ) ? sanitize_text_field( (string) $target['new_version'] ) : '',
			);
		}

		return $normalized;
	}

	/**
	 * Count target types.
	 *
	 * @param array $targets Normalized targets.
	 * @return array
	 */
	protected function count_types( array $targets ) {
		$counts = array(
			'plugin'  => 0,
			'theme'   => 0,
			'core'    => 0,
			'unknown' => 0,
		);

		foreach ( $targets as $target ) {
			$type = isset( $target['type'] ) && isset( $counts[ $target['type'] ] ) ? $target['type'] : 'unknown';
			++$counts[ $type ];
		}

		return $counts;
	}

	/**
	 * Summarize version jumps.
	 *
	 * @param array $targets Normalized targets.
	 * @return array
	 */
	protected function summarize_version_risk( array $targets ) {
		$summary = array(
			'major_updates'   => array(),
			'minor_updates'   => array(),
			'unknown_updates' => array(),
		);

		foreach ( $targets as $target ) {
			$current = isset( $target['current_version'] ) ? (string) $target['current_version'] : '';
			$new     = isset( $target['new_version'] ) ? (string) $target['new_version'] : '';
			$label   = isset( $target['label'] ) && '' !== $target['label'] ? (string) $target['label'] : ( isset( $target['slug'] ) ? (string) $target['slug'] : '' );

			if ( '' === $current || '' === $new ) {
				$summary['unknown_updates'][] = $label;
				continue;
			}

			$current_major = $this->major_version( $current );
			$new_major     = $this->major_version( $new );

			if ( null !== $current_major && null !== $new_major && $new_major > $current_major ) {
				$summary['major_updates'][] = $label;
			} else {
				$summary['minor_updates'][] = $label;
			}
		}

		return $summary;
	}

	/**
	 * Summarize source/package validation risk.
	 *
	 * @param array $validation Validation payload.
	 * @return array
	 */
	protected function summarize_source_risk( array $validation ) {
		$status  = isset( $validation['status'] ) ? sanitize_key( (string) $validation['status'] ) : '';
		$summary = isset( $validation['summary'] ) && is_array( $validation['summary'] ) ? $validation['summary'] : array();

		return array(
			'status'       => $status,
			'pass'         => isset( $summary['pass'] ) ? absint( $summary['pass'] ) : 0,
			'warning'      => isset( $summary['warning'] ) ? absint( $summary['warning'] ) : 0,
			'fail'         => isset( $summary['fail'] ) ? absint( $summary['fail'] ) : 0,
			'needs_review' => in_array( $status, array( 'warning', 'fail' ), true ) || ! empty( $summary['warning'] ) || ! empty( $summary['fail'] ),
		);
	}

	/**
	 * Summarize optional changelog context.
	 *
	 * @param array $targets       Normalized targets.
	 * @param array $changelog_map Changelog map.
	 * @return array
	 */
	protected function summarize_changelog_context( array $targets, array $changelog_map ) {
		$summary = array(
			'available'       => 0,
			'missing'         => 0,
			'security_terms'  => array(),
			'breaking_terms'  => array(),
			'unknown_targets' => array(),
		);

		foreach ( $targets as $target ) {
			$key   = isset( $target['key'] ) ? (string) $target['key'] : '';
			$label = isset( $target['label'] ) && '' !== $target['label'] ? (string) $target['label'] : $key;
			$text  = isset( $changelog_map[ $key ] ) ? strtolower( wp_strip_all_tags( (string) $changelog_map[ $key ] ) ) : '';

			if ( '' === trim( $text ) ) {
				++$summary['missing'];
				$summary['unknown_targets'][] = $label;
				continue;
			}

			++$summary['available'];

			if ( false !== strpos( $text, 'security' ) || false !== strpos( $text, 'vulnerability' ) || false !== strpos( $text, 'cve' ) ) {
				$summary['security_terms'][] = $label;
			}

			if ( false !== strpos( $text, 'breaking' ) || false !== strpos( $text, 'deprecated' ) || false !== strpos( $text, 'requires' ) ) {
				$summary['breaking_terms'][] = $label;
			}
		}

		return $summary;
	}

	/**
	 * Resolve deterministic risk level.
	 *
	 * @param array $targets      Normalized targets.
	 * @param array $type_counts  Type counts.
	 * @param array $version_risk Version risk.
	 * @param array $source_risk  Source risk.
	 * @param array $changelog    Changelog summary.
	 * @return string
	 */
	protected function resolve_risk_level( array $targets, array $type_counts, array $version_risk, array $source_risk, array $changelog ) {
		if ( empty( $targets ) ) {
			return 'low';
		}

		if ( ! empty( $source_risk['fail'] ) || 'fail' === $source_risk['status'] || ! empty( $type_counts['core'] ) ) {
			return 'high';
		}

		if ( count( $targets ) >= 5 || ! empty( $version_risk['major_updates'] ) || ! empty( $changelog['breaking_terms'] ) ) {
			return 'high';
		}

		if ( ! empty( $source_risk['warning'] ) || 'warning' === $source_risk['status'] || count( $targets ) >= 2 || ! empty( $changelog['security_terms'] ) || ! empty( $changelog['missing'] ) ) {
			return 'medium';
		}

		return 'low';
	}

	/**
	 * Build a title.
	 *
	 * @param string $risk_level Risk level.
	 * @param array  $context    Context.
	 * @return string
	 */
	protected function build_title( $risk_level, array $context ) {
		$scope = isset( $context['scope'] ) && '' !== trim( (string) $context['scope'] ) ? sanitize_text_field( (string) $context['scope'] ) : __( 'Update Risk', 'zignites-sentinel' );

		return $scope . ': ' . $this->humanize( $risk_level );
	}

	/**
	 * Build overview text.
	 *
	 * @param string $risk_level Risk level.
	 * @param array  $targets    Targets.
	 * @param array  $type_counts Type counts.
	 * @return string
	 */
	protected function build_overview( $risk_level, array $targets, array $type_counts ) {
		return sprintf(
			/* translators: 1: risk level, 2: total targets, 3: plugin count, 4: theme count, 5: core count */
			__( '%1$s risk across %2$d update target(s): %3$d plugin(s), %4$d theme(s), and %5$d core update(s).', 'zignites-sentinel' ),
			$this->humanize( $risk_level ),
			count( $targets ),
			(int) $type_counts['plugin'],
			(int) $type_counts['theme'],
			(int) $type_counts['core']
		);
	}

	/**
	 * Build findings.
	 *
	 * @param array $type_counts Type counts.
	 * @param array $version_risk Version risk.
	 * @param array $source_risk Source risk.
	 * @param array $changelog Changelog summary.
	 * @return array
	 */
	protected function build_findings( array $type_counts, array $version_risk, array $source_risk, array $changelog ) {
		$findings = array();

		if ( ! empty( $type_counts['core'] ) ) {
			$findings[] = array(
				'key'     => 'core_boundary',
				'status'  => 'warning',
				'message' => __( 'WordPress core is present in the update context, but Sentinel rollback covers plugin/theme code only.', 'zignites-sentinel' ),
			);
		}

		if ( ! empty( $version_risk['major_updates'] ) ) {
			$findings[] = array(
				'key'     => 'major_versions',
				'status'  => 'warning',
				'message' => sprintf(
					/* translators: %d: major update count */
					__( '%d update target(s) appear to cross a major version boundary.', 'zignites-sentinel' ),
					count( $version_risk['major_updates'] )
				),
			);
		}

		if ( ! empty( $source_risk['needs_review'] ) ) {
			$findings[] = array(
				'key'     => 'source_validation',
				'status'  => ! empty( $source_risk['fail'] ) || 'fail' === $source_risk['status'] ? 'critical' : 'warning',
				'message' => sprintf(
					/* translators: 1: warning count, 2: fail count */
					__( 'Source/package validation reports %1$d warning(s) and %2$d failure(s).', 'zignites-sentinel' ),
					(int) $source_risk['warning'],
					(int) $source_risk['fail']
				),
			);
		}

		if ( ! empty( $changelog['breaking_terms'] ) ) {
			$findings[] = array(
				'key'     => 'breaking_changelog',
				'status'  => 'warning',
				'message' => __( 'Changelog context includes breaking, deprecated, or requirement-change language.', 'zignites-sentinel' ),
			);
		}

		return $findings;
	}

	/**
	 * Build next steps.
	 *
	 * @param string $risk_level Risk level.
	 * @param array  $source_risk Source risk.
	 * @param array  $changelog Changelog summary.
	 * @return array
	 */
	protected function build_next_steps( $risk_level, array $source_risk, array $changelog ) {
		$steps = array(
			__( 'Create or confirm a fresh Sentinel checkpoint before running updates.', 'zignites-sentinel' ),
			__( 'Use this summary for review only; Sentinel must not auto-update or auto-rollback based on AI or risk text.', 'zignites-sentinel' ),
		);

		if ( 'high' === $risk_level ) {
			$steps[] = __( 'Stage or split high-risk updates into smaller batches where possible.', 'zignites-sentinel' );
		}

		if ( ! empty( $source_risk['needs_review'] ) ) {
			$steps[] = __( 'Resolve source/package validation warnings before relying on rollback artifacts.', 'zignites-sentinel' );
		}

		if ( ! empty( $changelog['missing'] ) ) {
			$steps[] = __( 'Review changelog or vendor release notes manually for targets without local changelog context.', 'zignites-sentinel' );
		}

		return $steps;
	}

	/**
	 * Extract major version number.
	 *
	 * @param string $version Version string.
	 * @return int|null
	 */
	protected function major_version( $version ) {
		if ( preg_match( '/^v?(\d+)/i', (string) $version, $matches ) ) {
			return (int) $matches[1];
		}

		return null;
	}

	/**
	 * Humanize a status key.
	 *
	 * @param string $value Status key.
	 * @return string
	 */
	protected function humanize( $value ) {
		$value = trim( str_replace( array( '-', '_' ), ' ', (string) $value ) );

		return '' === $value ? '' : ucwords( $value );
	}
}
