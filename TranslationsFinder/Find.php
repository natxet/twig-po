<?php
namespace TranslationsFinder;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/* ./console find:tags /path/to/twig/tpls/ /path/to/Locale/en_US/LC_MESSAGES/routes.po Po -t "/{{(.*)}}/muU"
*/

class Find extends Command
{
    /* TO-DO: Plural is not working at all! */
    const TAG_REGEX = '/{% ?trans ?%}(.*)(?:{% ? plural (.*)?%}(.*))?{% ?endtrans ?%}/muU';
    const MODIFIER_REGEX = '/([a-zA-Z_0-9]+)|trans/muU';

    /**
     * @var int number of tags found in the templates (unique tags by file)
     */
    protected $n_found_tags = 0;

    /**
     * @var int number of read files
     */
    protected $n_read_files = 0;

    /**
     * @var int number of tags that were already in the messages file
     */
    protected $n_matched_tags = 0;

    // program options
    protected $dry_run = false;
    protected $verbose = false;
    protected $output_tags = false;
    protected $tag_regex;

    /**
     * @var Format\FormatInterface
     */
    protected $format;

    protected function configure()
    {
        $this->setName( 'find:tags' )->setDescription(
            'Find {%trans%} tags in a directory'
        )->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Please include the path where you want me to find tags'
        )->addArgument(
            'messages-filename',
            InputArgument::REQUIRED,
            'file for check and write to'
        )->addArgument(
            'format',
            InputArgument::REQUIRED,
            'Format to use, for instance "Po" (CamelCase)'
        )->addOption(
            'tag-regex',
            't',
            InputOption::VALUE_OPTIONAL,
            'Change regex for finding tag. By default: "' . self::TAG_REGEX . '"'
        )->addOption(
            'dry-run',
            'd',
            InputOption::VALUE_NONE,
            'Do not write the new tags in the messages file'
        )->addOption(
            'verbose',
            'v',
            InputOption::VALUE_NONE,
            'Output information of every step'
        )->addOption(
            'output-tags',
            'o',
            InputOption::VALUE_NONE,
            'Output the tags as they will appear in the final PO file'
        );
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        switch ($this->getName()) {

            case 'find:tags':
                $this->findTags( $input, $output );
        }
    }

    protected function findTags( InputInterface $input, OutputInterface $output )
    {
        $this->setOptions( $input );
        $this->setFormatObject( $input->getArgument( 'format' ) );

        $templates_path    = $this->filterTemplatesPath( $input->getArgument( 'path' ) );
        $messages_filename = $this->filterMessagesFilename( $input->getArgument( 'messages-filename' ) );
        $existing_tags     = $this->getKeysFromFile( $messages_filename );
        $n_existing_tags   = count( $existing_tags );
        $tags              = array();

        if ($this->verbose) {
            $output->writeln( "Found $n_existing_tags existing keys in <fg=green>$messages_filename</fg=green>" );
        }

        $this->searchDirectory( $templates_path, $tags, $existing_tags );

        $n_tags = count( $tags );

        if ($this->verbose) {
            $output->writeln(
                "Found $this->n_found_tags new tags in $this->n_read_files files under <fg=green>$templates_path</fg=green>"
            );
        }
        if ($this->verbose && $this->n_matched_tags) {
            $output->writeln(
                "<fg=magenta>$this->n_matched_tags tags were already in the PO file (can be repeated)</fg=magenta>"
            );
        }
        if ($this->verbose) {
            $output->writeln( "Prepared to include <fg=green>$n_tags tags</fg=green>" );
        }

        if ($n_tags) {

            $output_tags = $this->outputTags( $tags );

            if ($this->output_tags) {
                echo $output_tags;
            }

            if ($this->dry_run) {
                if ($this->verbose) {
                    $output->writeln( "<fg=yellow>Dry-run: PO file will not be touched</fg=yellow>" );
                }
            } else {
                file_put_contents( $messages_filename, file_get_contents( $messages_filename ) . $output_tags );
                if ($this->verbose) {
                    $output->writeln( "<fg=magenta>PO FILE UPDATED!</fg=magenta>" );
                }
            }

            // TO-DO: hacer lo de arriba bien!
        }
    }

    /**
     * Given a format, initiates an object implementing FormatInterface
     *
     * @param string $format The format (f.i. 'Po')
     */
    protected function setFormatObject( $format )
    {
        $class_name   = "TranslationsFinder\\Format\\{$format}Format";
        $this->format = new $class_name();
    }

    /**
     * Takes the options from the command line and saves the attributes for latter use
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     */
    protected function setOptions( InputInterface $input )
    {
        $this->dry_run     = $input->getOption( 'dry-run' );
        $this->verbose     = $input->getOption( 'verbose' );
        $this->output_tags = $input->getOption( 'output-tags' );
        $regex             = $input->getOption( 'tag-regex' );
        $this->tag_regex   = $regex ? $regex : self::TAG_REGEX;
    }

    /**
     * This function tries to read the messages file. If the file does not exist, it creates a blank one.
     *
     * @param string $file_name The file name of the messages
     *
     * @return string The File name
     * @throws \InvalidArgumentException
     */
    protected function filterMessagesFilename( $file_name )
    {
        if (!file_exists( $file_name ) && !touch( $file_name )) {

            throw new \InvalidArgumentException( "ERROR: could not read or touch $file_name messages file" );
        }
        return $file_name;
    }

    /**
     * If the path does not exist, throws an exception.
     * If the path ends in DIRECTORY_SEPARATOR, removes it.
     *
     * @param string $templates_path The path to search for templates
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function filterTemplatesPath( $templates_path )
    {
        if (!file_exists( $templates_path )) {
            throw new \InvalidArgumentException( "ERROR: $templates_path templates path does not exist" );
        }

        if (strrpos( $templates_path, DIRECTORY_SEPARATOR ) == strlen( $templates_path ) - 1) {
            $templates_path = substr( $templates_path, 0, -1 );
        }
        return $templates_path;
    }

    /**
     * Reads all translations in the messages file and returns an array with the keys
     */
    protected function getKeysFromFile( $file_name )
    {
        return $this->format->parseMessagesFile( file_get_contents( $file_name ) );
    }

    // TO-DO: accept several regex
    // TO-DO: option for returning an array_unique
    protected function pregMatchAllFile( $file_name, $regex )
    {
        preg_match_all( $regex, file_get_contents( $file_name ), $matches );
        return ( isset( $matches[1] ) ) ? $matches[1] : array();
    }

    protected function outputTags( $tags )
    {
        $output = "";
        foreach ( $tags as $tag => $file_names ) {

            $output .= $this->outputTag( $tag, $file_names );
        }
        return $output;
    }

    protected function outputTag( $tag, $file_names = array() )
    {
        return $this->format->outputTag( $tag, $file_names );
    }

    // TO-DO: Allow plurals!
    protected function addTag( &$tags, $tag, $file_name )
    {
        if (!array_key_exists( $tag, $tags )) {

            $tags[$tag] = array( $file_name );
            $this->n_found_tags++;

        } else {

            $tags[$tag][] = $file_name;
        }
    }

    protected function parseTag( $tag )
    {
        return $this->format->parseTag( $tag );
    }

    protected function parseFile( $filename, &$tags, $existing_tags )
    {
        $this->n_read_files++;
        $matches = array_unique( $this->pregMatchAllFile( $filename, $this->tag_regex ) );

        foreach ($matches as $tag) {

            if (in_array( $this->format->outputString( $tag ), $existing_tags )) {
                $this->n_matched_tags++;
                continue;
            }
            $tag = $this->parseTag( $tag );
            $this->addTag( $tags, $tag, $filename );
        }
    }

    protected function searchDirectory( $path, &$tags, $existing_tags )
    {

        if (is_file( $path )) {
            $this->parseFile( $path, $tags, $existing_tags );
        } elseif (is_dir( $path )) {

            foreach ( new \DirectoryIterator( $path ) as $fileinfo ) {

                $filename      = $fileinfo->getFilename();
                $full_filename = $path . DIRECTORY_SEPARATOR . $filename;

                if ($filename === '.' || $filename === '..') {
                    continue;
                } elseif ($fileinfo->isDir()) {
                    $this->searchDirectory( $full_filename, $tags, $existing_tags );
                } elseif ($fileinfo->isFile()) {
                    $this->parseFile( $full_filename, $tags, $existing_tags );
                }
            }
        }
    }
}
