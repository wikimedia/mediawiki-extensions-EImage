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

class GetImage extends Maintenance {
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

	public function execute() {
		$image = new EImageIMG;
		// Vyplivnutí verze
		// echo $image->getExiftoolVersion();

		if ( $this->getOption( 'crop' ) ) {
			$image->setEidCrop( $this->getOption( 'crop' ) );
		} else {
			$image->setEidAxes( $this->getOption( 'XY' ) );
			$image->setEidArea( $this->getOption( 'area' ) );
		}
		if ( $this->getOption( 'width' ) ) {
			$image->setEidWidth( $this->getOption( 'width' ) );
		}
		$image->setEidSource( $this->getOption( 'image' ) );
		return $image->getImage();
		echo "Done\n";
		return true;
	}

}

$maintClass = GetImage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
