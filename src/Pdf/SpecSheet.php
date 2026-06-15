<?php
/**
 * Renders a single asset as a printable PDF spec sheet.
 *
 * @package AssetRegistry
 */

declare( strict_types=1 );

namespace AssetRegistry\Pdf;

use AssetRegistry\Asset;
use AssetRegistry\Category;
use AssetRegistry\Status;
use Dompdf\Dompdf;

/**
 * Builds the spec-sheet HTML and turns it into PDF bytes. The HTML builder is
 * kept pure so it can be unit-tested; the Dompdf rendering is a thin wrapper
 * verified manually.
 */
final class SpecSheet {

	/**
	 * Builds the self-contained HTML document for one asset's spec sheet.
	 *
	 * The markup uses only inline styles on a simple table because Dompdf
	 * supports a limited CSS subset (no flexbox or grid). Every dynamic value
	 * is escaped with esc_html().
	 *
	 * @param Asset $asset The asset to describe.
	 * @return string A complete HTML document.
	 */
	public function build_html( Asset $asset ): string {
		$category = Category::is_valid( $asset->category )
			? Category::from( $asset->category )->label()
			: $asset->category;

		$status = Status::is_valid( $asset->status )
			? Status::from( $asset->status )->label()
			: $asset->status;

		$purchase_date = ( null === $asset->purchase_date || '' === $asset->purchase_date )
			? '-'
			: $asset->purchase_date;

		$rows = array(
			'Asset Tag'     => $asset->asset_tag,
			'Name'          => $asset->name,
			'Category'      => $category,
			'Status'        => $status,
			'Location'      => $asset->location,
			'Assigned To'   => $asset->assigned_to,
			'Purchase Date' => $purchase_date,
			'Value'         => number_format( $asset->value, 2 ),
			'Notes'         => $asset->notes,
		);

		$body = '';
		foreach ( $rows as $label => $value ) {
			$body .= sprintf(
				'<tr><th style="text-align:left;padding:8px 12px;border:1px solid #ccc;background:#f4f4f4;width:30%%;">%s</th><td style="padding:8px 12px;border:1px solid #ccc;">%s</td></tr>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		return '<!DOCTYPE html>'
			. '<html><head><meta charset="utf-8">'
			. '<title>' . esc_html( 'Asset Spec Sheet' ) . '</title>'
			. '</head>'
			. '<body style="font-family:DejaVu Sans, sans-serif;color:#222;font-size:13px;">'
			. '<h1 style="font-size:20px;margin:0 0 4px;">' . esc_html( 'Asset Spec Sheet' ) . '</h1>'
			. '<p style="margin:0 0 16px;font-size:15px;color:#555;">' . esc_html( $asset->name ) . '</p>'
			. '<table style="border-collapse:collapse;width:100%;">'
			. $body
			. '</table>'
			. '</body></html>';
	}

	/**
	 * Produces the PDF bytes for an asset. Verified manually because it
	 * depends on the Dompdf rendering pipeline.
	 *
	 * @param Asset $asset The asset to render.
	 * @return string The raw PDF document bytes.
	 */
	public function render( Asset $asset ): string {
		$dompdf = new Dompdf();
		$dompdf->loadHtml( $this->build_html( $asset ) );
		$dompdf->setPaper( 'A4' );
		$dompdf->render();

		return (string) $dompdf->output();
	}

	/**
	 * Builds a safe download filename for an asset's spec sheet.
	 *
	 * @param Asset $asset The asset being downloaded.
	 * @return string A sanitized "asset-{tag}.pdf" filename.
	 */
	public function filename( Asset $asset ): string {
		return sanitize_file_name( 'asset-' . $asset->asset_tag . '.pdf' );
	}
}
