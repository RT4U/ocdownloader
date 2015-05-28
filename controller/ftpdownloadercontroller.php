<?php
/**
 * ownCloud - ocDownloader
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Xavier Beurois <www.sgc-univ.net>
 * @copyright Xavier Beurois 2015
 */

namespace OCA\ocDownloader\Controller;

use \OCP\IRequest;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\AppFramework\Controller;
use \OCP\Config;

use \OCA\ocDownloader\Controller\Lib\Aria2;
use \OCA\ocDownloader\Controller\Lib\Tools;

class FtpDownloaderController extends Controller
{
      private $TargetFolder;
      private $DbType;
      
      public function __construct ($AppName, IRequest $Request, $UserStorage)
      {
            parent::__construct ($AppName, $Request);
            $this->TargetFolder = Config::getSystemValue ('datadirectory') . $UserStorage->getPath ();
            
            $this->DbType = 0;
            if (strcmp (Config::getSystemValue ('dbtype'), 'pgsql') == 0)
            {
                  $this->DbType = 1;
            }
      }
      
      /**
       * @NoAdminRequired
       * @NoCSRFRequired
       */
      public function add ()
      {
            if (isset ($_POST['URL']) && strlen ($_POST['URL']) > 0 && Tools::CheckURL ($_POST['URL']) && isset ($_POST['OPTIONS']))
            {
                  try
                  {
                        $Target = substr($_POST['URL'], strrpos($_POST['URL'], '/') + 1);
                        
                        // If target file exists, create a new one
                        if (\OC\Files\Filesystem::file_exists ($Target))
                        {
                              $Target = $Target . '.' . time ();
                        }
                        
                        // Create the target file
                        \OC\Files\Filesystem::touch ($Target);
                        
                        // Download in the /tmp folder
                        $OPTIONS = Array ('dir' => $this->TargetFolder, 'out' => $Target);
                        if (isset ($_POST['OPTIONS']['FTPUser']) && strlen (trim ($_POST['OPTIONS']['FTPUser'])) > 0 && isset ($_POST['OPTIONS']['FTPPasswd']) && strlen (trim ($_POST['OPTIONS']['FTPPasswd'])) > 0)
                        {
                              $OPTIONS['ftp-user'] = $_POST['OPTIONS']['FTPUser'];
                              $OPTIONS['ftp-passwd'] = $_POST['OPTIONS']['FTPPasswd'];
                        }
                        if (isset ($_POST['OPTIONS']['FTPPasv']) && strlen (trim ($_POST['OPTIONS']['FTPPasv'])) > 0)
                        {
                              $OPTIONS['ftp-pasv'] = strcmp ($_POST['OPTIONS']['FTPPasv'], "true") == 0 ? true : false;
                        }
                        
                        $Aria2 = new Aria2();
                        $AddURI = $Aria2->addUri (Array ($_POST['URL']), $OPTIONS);
                        
                        if (isset ($AddURI["result"]) && !is_null ($AddURI["result"]))
                        {
                              $SQL = 'INSERT INTO `*PREFIX*ocdownloader_queue` (GID, FILENAME, PROTOCOL, STATUS, TIMESTAMP) VALUES (?, ?, ?, ?, ?)';
                              if ($this->DbType == 1)
                              {
                                    $SQL = 'INSERT INTO *PREFIX*ocdownloader_queue ("GID", "FILENAME", "PROTOCOL", "STATUS", "TIMESTAMP") VALUES (?, ?, ?, ?, ?)';
                              }
                              
                              $Query = \OCP\DB::prepare ($SQL);
                              $Result = $Query->execute (Array (
                                    $AddURI["result"],
                                    $Target,
                                    strtoupper(substr($_POST['URL'], 0, strpos($_POST['URL'], ':'))),
                                    1,
                                    time()
                              ));
                              
                              die (json_encode (Array ('ERROR' => false, 'MESSAGE' => 'Download has been launched', 'NAME' => $Target, 'GID' => $AddURI["result"], 'PROTO' => strtoupper(substr($_POST['URL'], 0, strpos($_POST['URL'], ':'))), 'SPEED' => '...')));
                        }
                        else
                        {
                              die (json_encode (Array ('ERROR' => true, 'MESSAGE' => 'Returned GID is null ! Is Aria2c running as a daemon ?')));
                        }
                  }
                  catch (Exception $E)
                  {
                        die (json_encode (Array ('ERROR' => true, 'MESSAGE' => $E->getMessage ())));
                  }
            }
            else
            {
                  die (json_encode (Array ('ERROR' => true, 'MESSAGE' => 'Please check the URL you\'ve just provided')));
            }
      }
}