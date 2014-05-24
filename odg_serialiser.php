<?php 

ini_set('display_errors', 0);
error_reporting(-1);
zip_lib_check();

if ($_POST['submit']) {

	###		SETTINGS
	$extract_dir	= './extract/';
	$build_dir		= './rebuild/';

	$sequence		= $_POST['sequence'];
	$replace 		= $_POST['replace'];

	###		Hash the file to generate "unique" string for the processing folder
	$file_hash = md5_file ( $_FILES['odgfile']['tmp_name'] );

	###		Build the processing folder path
	$dir_path = $extract_dir . $file_hash . '/';

	###		Unzip the ODT file
	odt_unpack ( $_FILES['odgfile']['tmp_name'], $dir_path );

	###		Open the 'content.xml' file and read it
	$fp = fopen ( $dir_path . 'content.xml', 'r' );
	$content = fread ( $fp, filesize ( $dir_path . 'content.xml' ) );

	###		Split the content of the file into an array (header, page, footer)
	$page = odg_explode ( $content, '<draw:page', '</draw:page>' );

	###		Initialise the new document content with the original header
	$newdoc = (string) $page[0];

	###		Cycle through the number of iterations specified
	for ( $i = $sequence['start']; $i <= $sequence['end']; $i++ ) {
		###		Replace the first string with the index value
		$cur_page = str_replace ( $sequence['source'], $i, $page[1] );
		###		Replace each subsequent strings with the specified constant value
		foreach ( $replace as $pairs ) {
			$cur_page = str_replace ( $pairs['source'], $pairs['replace'], $cur_page );
		}
		###		Committ the changes to the new document content
		$newdoc .= $cur_page;
	}

	###		Finalise the new document content with the original footer
	$newdoc .= $page[2];

	###		Write the new content to 'content.xml'
	$fp = fopen( $dir_path . 'content.xml', 'w' );
	fwrite ( $fp, $newdoc );
	fclose ( $fp );

	###		Create the new file name
	$newDocumentFile = $build_dir . $file_hash . '.odg';

	###		Build the new ODT file
	odt_repack ( $dir_path, $newDocumentFile );

	###		Delete the source folder
	rrmdir ( $dir_path );

	###		Send the newly create file for download
	if (file_exists($newDocumentFile)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.basename($newDocumentFile));
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($newDocumentFile));
		ob_clean();
		flush();
		readfile($newDocumentFile);
		unlink($newDocumentFile);
	}
}

function zip_lib_check ( ) {
	if (!extension_loaded('zip')) {
		if (!dl('zip.so')) {
			exit('For this script to work, the PHP ZIP extension must be loaded.');
		}
	}
}

function odt_unpack ( $source, $destination ) {
	$zip = new ZipArchive();
	$zip->open($source);
	$zip->extractTo($destination);
	return $zip->close();
}

function odt_repack ( $source, $destination ) {
	if (file_exists($source) === true) {
		$zip = new ZipArchive();
		if ($zip->open($destination, ZIPARCHIVE::CREATE) === true) {
			$source = realpath($source);
			if (is_dir($source) === true) {
				$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
				foreach ($files as $file) {
					$file = realpath($file);
					if (is_dir($file) === true) {
						$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
					}
					else if (is_file($file) === true) {
						$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
					}
				}
			}
			else if (is_file($source) === true) {
				$zip->addFromString(basename($source), file_get_contents($source));
			}
		}
		return $zip->close();
	}
}

function odg_explode ( $string, $start, $end ) {
	$ini = strpos( $string, $start );
	$len = strpos( $string, $end, $ini) - $ini;
	$len += strlen( $end );
	return array(
		substr( $string, 0, $ini - strlen( $ini ) + 4 ),
		substr( $string, $ini, $len ),
		substr( $string, $ini - strlen ( $ini ) + 4 + $len, strlen ( $string ) )
	);
}

function rrmdir( $dir ) {
	foreach( glob( $dir . '/*' ) as $file ) {
		if ( is_dir( $file ) )
			rrmdir( $file );
		else
			unlink( $file );
	}
	rmdir( $dir );
}

?>

<!DOCTYPE html>

<html>

<head>
	<title>ODG Manipulator</title>
	<style>
		#successful {
			background-color: green;
			color: white;
			text-transform: uppercase;
		}
		.replaceVar {
		 	cursor: pointer;
		 	border: 1px solid red;
		 }
		.removeVar {
			cursor: pointer;
			background-color: black;
			color: red;
			font-family: "Arial";
		}
		.addVar {
			border: 2px solid red;
			cursor: pointer;
		}
	</style>
</head>

<body>

	<header>
		<h1>ODG Manipulator</h1>
	</header>

	<div style="">operation completed successfully</div>

	<form id="odg_manipulator" name="odg_manipulator" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
		<p>
			<input type="submit" name="submit">
			<input type="file" name="odgfile" id="odgfile" accept="application/vnd.oasis.opendocument.graphics" autofocus>
		</p>
		<p>
			<span>Number sequence</span>
			<br>
			<label for="sequence_source">Replace</label>
			<input type="text" name="sequence[source]" id="sequence_source'" value="<?php echo $_POST['sequence']['source']; ?>" required>
			<label for="sequence_start">starting</label>
			<input type="number" name="sequence[start]" id="sequence_start" value="<?php echo $_POST['sequence']['start']; ?>" required>
			<label for="sequence_end">until</label>
			<input type="number" name="sequence[end]" id="sequence_end" value="<?php echo $_POST['sequence']['end']; ?>" required>
		</p>

<?php

if ($_POST['replace']) {
	$i = 0;
	foreach ($_POST['replace'] as $replace) {
		if ($replace['source'] !== '') {

?>

		<p class="replaceVar">
			<label for="replace_source_<?php echo $i; ?>">Replace</label>
			<input type="text" name="replace[<?php echo $i; ?>][source]" id="replace_source_<?php echo $i; ?>" value="<?php echo $replace['source']; ?>">
			<label for="replace_<?php echo $i; ?>">with</label>
			<input type="text" name="replace[<?php echo $i; ?>][replace]" id="replace_replace_<?php echo $i; ?>" value="<?php echo $replace['replace']; ?>">
			<span class="removeVar" title="Remove this module">REMOVE</span>
		</p>

<?php

			$i++;
		}
	}
}

else $i=0;

?>

		<p>
			<span id="addVar" class="addVar">Add replacement module</span>
		</p>
	</form>

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
	<script>
		var startingNo	=	0;
		var $node		=	"";
		varCount		=	<?php echo $i; ?>;
		$('form').prepend($node);
		
		$('form').on('click', '.removeVar', function(){
			$(this).parent().remove();
		});
	
		$('#addVar').on('click', function(){
			//new node
			varCount++;
			$node = '\
				<p class="replaceVar">\
					<label for="replace_source_'+varCount+''+varCount+'">Replace</label>\
					<input type="text" name="replace['+varCount+'][source]" id="replace_source_'+varCount+'">\
					<label for="replace_replace_'+varCount+''+varCount+'">with</label>\
					<input type="text" name="replace['+varCount+'][replace]" id="replace_replace_'+varCount+'">\
					<span class="removeVar" title="Remove this module">REMOVE</span>\
				</p>\
			';
			$(this).parent().before($node);
		});
	</script>

</body>
</html>