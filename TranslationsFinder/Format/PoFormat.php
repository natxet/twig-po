<?php
namespace TranslationsFinder\Format;

class PoFormat implements FormatInterface {

    const MSGID_REGEX    = '/msgid "(.*)"/mu';

    /**
     * @param string $file_contents the file in string
     *
     * @return array the messages (only the keys)
     */
    public function parseMessagesFile( $file_contents )
    {
        preg_match_all( self::MSGID_REGEX, $file_contents, $matches );
        return ( isset( $matches[1] ) ) ? $matches[1] : array();
    }

    /**
     * @param string $tag the tag as it's found in the template
     *
     * @return string The tag parsed for saving in the messages_file
     */
    public function parseTag( $tag )
    {
        return preg_replace( array( '/{{ (.*) }}/muU', '/{{(.*)}}/muU' ), '%\1%', $tag );
    }

    /**
     * @param string $tag        The tag for saving
     * @param array  $file_names The appearances in files of the tag
     *
     * @return string The output
     */
    public function outputTag( $tag, $file_names = array() )
    {
        $output = '';
        foreach ( $file_names as $filename ) {

            $output .= <<<EOT

#: $filename
EOT;

        }

        $output .= <<<EOT

msgid "$tag"
msgstr ""

EOT;
        return $output;
    }
}
