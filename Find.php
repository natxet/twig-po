<?php
namespace TranslationsFinder;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Find extends Command
{
    const DEFAULT_TAG = 'trans';
    const DEFAULT_TAG_REGEX = "/{% ?__TAG__ ?%}(.*){% ?end__TAG__ ?%}/muU";

    protected $tag_regex;
    protected $tags = array();
    protected $last_filename;

    protected function configure()
    {
        $this
            ->setName('find:tags')
            ->setDescription('Find {%trans%} tags in a directory')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Please include the path where you want me to find tags'
            )
            ->addArgument(
                'po-file',
                InputArgument::REQUIRED,
                'PO file for check and write to'
            )
	    ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Just output the results. Otherwise, the PO file will be writen'
            )
            ->addOption(
                'tag',
                't',
                InputOption::VALUE_OPTIONAL,
                'Look for a tag name, by default, "trans". For closing, will be "end".tag'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
	$path = $input->getArgument('path');
	if ( !file_exists( $path ) ) {
		Throw new \InvalidArgumentException("ERROR: $path does not exist");
	}
	if ( strrpos( $path, DIRECTORY_SEPARATOR) == strlen( $path ) -1 ) {
		$path = substr( $path, 0, -1 ); 
	}

	$po_filename = $input->getArgument('po-file');
	
	( $dry_run = $input->getOption('dry-run') ) or $dry_run = false;
	( $tag = $input->getOption('tag') ) or $tag = self::DEFAULT_TAG;

	$output->writeln("<fg=green>Searching for {%$tag%} tags in $path recursively</fg=green>");

	$this->setTagRegex( $tag );
	$read_files = $this->searchDirectory( $path );
	
	$tags = $this->tags;
	$output->writeln("<fg=green>Finished search! Found " . count($tags) . " tags in $read_files files</fg=green>");

	if( $po_filename ) {

                if ( !file_exists( $po_filename ) ) Throw new \InvalidArgumentException("ERROR: $po_filename PO FILE does not exist");
                $output->writeln("<fg=magenta>Filtering Existing msgids from $po_filename</fg=magenta>");
                $existing_tags = $this->getMsgIdsFromFile( $po_filename );
		$tags = $this->filterExistingTags( $tags, $existing_tags );
		$deleted = count($this->tags) - count($tags);
		$output->writeln("<fg=magenta>Deleted " . $deleted . " tags from " . count($existing_tags) . " existing tags in the PO file</fg=magenta>"); 
	}

	$output->writeln("<fg=green>Outputing " . count($tags) . " tags</fg=green>");
	if( count( $tags ) ) {

		$output_tags = $this->outputTags( $tags );
		if( $dry_run ) {
			$output->writeln("<fg=yellow>Dry-run! PO file will not be touched! Showing output:</fg=yellow>");
			echo $output_tags;
		}
		else {
			file_put_contents( $po_filename, file_get_contents( $po_filename ) . $output_tags );
			$output->writeln("<fg=magenta>PO FILE UPDATED!</fg=magenta>");
		}
		// TO-DO: hacer lo de arriba bien!
	}
    }

    protected function filterExistingTags( $tags, $existing_tags ) {

	$new = array_diff( array_keys( $tags ), $existing_tags );

	foreach( $tags as $tag => $files ) {
		if( in_array( $tag, $existing_tags ) ) unset( $tags[$tag] );
	}

	return $tags;
    }

    /**
    * Reads all translations in the PO file and returns an array with msgid
    */
    protected function getMsgIdsFromFile( $filename ) {

	$msg_ids = array();
	$file_handle = fopen( $filename, 'r');
	while ( !feof( $file_handle ) ) {
	   $line = fgets( $file_handle );
	   if ( strpos( trim( $line ), 'msgid' ) === 0 ) {
		preg_match( '/msgid "(.*)"/mu', $line, $matches );
		if( isset( $matches[1] ) ) $msg_ids[] = $matches[1];
	   }
	}
	fclose( $file_handle );
	return $msg_ids;
    }

    protected function outputTags( $tags ) {
	
	$output = "";
	foreach( $tags as $tag => $filenames ) {
		$output .= $this->outputTag( $tag, $filenames);
	}
	return $output;
    }

    protected function outputTag( $tag, $filenames = array() ) {

	$output = '';
	foreach( $filenames as $filename ) {
		$output .= "\n# $filename";
	}
	$output .= <<<EOT

msgid "$tag"
msgstr ""

EOT;
	return $output;
    }

    protected function setTagRegex( $tag ) {

	$this->tag_regex = str_replace( '__TAG__', $tag, self::DEFAULT_TAG_REGEX );
    }

    protected function searchFile( $filename ) {

	$contents = file_get_contents( $filename );
	$res = preg_match_all( $this->tag_regex, $contents, $matches );
	if ($res && isset( $matches[1] )){

		foreach( array_unique( $matches[1] ) as $tag) {
			$this->addTag( $tag, $filename );
		}
	}
    }

    protected function addTag( $tag, $filename ) {

	// this is for the PO format of the variables
        $tag = preg_replace( array('/{{ (.*) }}/muU', '/{{(.*)}}/muU'), '%\1%', $tag );

	if( !array_key_exists( $tag, $this->tags ) ) {
            $this->tags[$tag] = array( $filename );
        }
        else {
            $this->tags[$tag][] = $filename;
        }

    }

    protected function searchDirectory( $path ) {

	if( is_file( $path ) ) {
		$this->searchFile( $path );
		return 1;
	}
	$read_files = 0;
	foreach(new \DirectoryIterator( $path ) as $fileinfo ){
		
		$filename = $fileinfo->getFilename();

		if ( $filename === '.' || $filename === '..' ) {
			continue;
		}
		elseif ( $fileinfo->isDir() ) {
			$read_files += $this->searchDirectory( $path . DIRECTORY_SEPARATOR. $filename );
		}
		elseif ( $fileinfo->isFile() ) {
			$this->searchFile( $path . DIRECTORY_SEPARATOR . $filename );
			$read_files++;
		}
	}
	return $read_files;
    }
}
