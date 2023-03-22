<?php
/*
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 */
if ( getenv( 'MW_INSTALL_PATH' ) ) {
        $IP = getenv( 'MW_INSTALL_PATH' );
} else {
        $IP = __DIR__ . '/../../..';
}

$path = '../../..';

if ( getenv( 'MW_INSTALL_PATH' ) !== false ) {
	$path = getenv( 'MW_INSTALL_PATH' );
}

require_once $path . '/maintenance/Maintenance.php';

//use MWException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\EImage;
// kvůli dekódování json výstupu z nástroje exiftool;
use MediaWiki\Json\JsonUnserializer;

class getImage extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'image', 'Image for rework', true, true );
		$this->addOption( 'target', 'Path for target image', false, true );
		$this->addOption( 'XY', 'Left top corner of the image is the zero point', false, true );
		$this->addOption( 'area', 'Area from image', false, true );
		$this->addOption( 'width', 'Width for resample image', false, true );
		$this->addOption( 'crop', 'Crop sane as value of the #eimg attribute', false, true );
		$this->addOption( 'dry', 'Skip action crop and print values for it', false, false );
		$this->addDescription( 'Crop image from remote (or local) source and save to target path.
Example of remote path:

    https://www.thewoodcraft.org/wiki/images/d/d7/dot.png

and local absolute path:

    /srv/main/wiki/images/d/d7/dot.png
' );
		$this->requireExtension( 'EImage' );
	}

/*
	// pro lokální soubory bere pro obrázky plnou cestu od kořene
	// "/srv/main/wiki/extensions/EImage/empty.png"
	// pro vzdálené obrázky musí být uvedeno plné url
	// "https://www.thewoodcraft.org/wiki/images/d/d7/dot.png"
	isset($query['page'])
		? $source = 'https://' . $_SERVER[ 'HTTP_HOST' ] . '/wiki/thumb.php?' . http_build_query( array (
			'f'     => $query['f'],
			'page'  => $query['page'],
			'width' => $query['width']
			) )
		: $source = 'https://' . $_SERVER[ 'HTTP_HOST' ] . '/wiki/thumb.php?' . http_build_query( array (
			'f'     => $query['f'],
			'width' => $query['width']
		) ) ;

https://www.mediawiki.org/wiki/Manual:BoxedCommand

*/

	public function execute() {
		$image = new EImageIMG;
		// Vyplivnutí verze
		//echo $image->getExiftoolVersion();

		if ( $this->getOption( 'crop' ) ) {
			$image->setCrop( $this->getOption( 'crop' ) );
		} else {
			$image->setAxes( $this->getOption( 'XY' ) );
			$image->setArea( $this->getOption( 'area' ) );
		}
		if ( $this->getOption( 'width' ) ) $image->setSourceWidth( $this->getOption( 'width' ) );
		$image->setSource( $this->getOption( 'image' ) );
		return $image->getPath();
//		$image->cssImage();
		echo "Done\n";
		return;
	}

}

$maintClass = getImage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
