<?php
namespace Vendi\Admin\Synology\DuplicateFiles;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use League\Csv\Reader;

class DupeLogReader extends Command
{
    private $_log_file;

    protected function configure()
    {
        $this
            ->setName('dupe-log-reader')
            ->setDescription('Reads the duplicate log')
            ->addArgument( 'log-file',  InputArgument::REQUIRED, 'The log file to parse' )
        ;
    }

    protected function initialize( InputInterface $input, OutputInterface $output )
    {
        $this->_log_file = $input->getArgument( 'log-file' );
    }

    //From https://stackoverflow.com/a/2510459/231316
    public function formatBytes( $bytes, $precision = 2 )
    {
        $units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];

        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );

        $bytes /= pow( 1024, $pow );

        return round( $bytes, $precision ) . ' ' . $units[ $pow ];
    }

    protected function execute( InputInterface $input, OutputInterface $output )
    {
        //This is the delimiter for the file. You know, CSV, C = "Tab", right?
        $delim = "\t";

        //We don't want to flash the progress bar too much so we'll pick a
        //fairly high number.
        //NOTE: The progress bar technically slows this whole thing down because
        //we actually have to count the records which is a waste of time. But
        //that's progress!
        $progress_bar_every = 987;

        //This is used to output formatted text
        $io = new SymfonyStyle( $input, $output );

        //Create our PHP Leage CSV Reader
        $reader = Reader::createFromPath( $this->_log_file );

        //Explicitly set the delimiter
        $reader->setDelimiter( $delim );

        //Depending on the BOM we might need to manually convert things
        $bom = $reader->getInputBOM();
        switch( $bom )
        {
            case Reader::BOM_UTF16_LE:
            case Reader::BOM_UTF16_BE:
                $io->note( 'UTF-16 Little Endian content found, converting to UTF-8' );
                $reader->appendStreamFilter('convert.iconv.UTF-16/UTF-8');
                break;

            //Maybe these two exist but without a test file I'm not going
            //to bother
            case Reader::BOM_UTF32_BE:
                $io->note( 'UTF-16 Big Endian content found, converting to UTF-8' );
                $io->error( 'Unsupported BOM: Reader::BOM_UTF32_BE' );
                break;

            case Reader::BOM_UTF32_LE:
                $io->error( 'Unsupported BOM: Reader::BOM_UTF32_LE' );
                exit;
        }

        //Which row to grab the column headers from
        $offset_for_column_labels = 0;

        //Create an iterator
        $results = $reader->fetchAssoc( $offset_for_column_labels );

        //The "CSV" is weird. There's a bunch of file entries prefixed with a
        //"Duplicate Group" which is an int. Not every file entry in the CSV
        //appears to be a duplicate file, however, so we're going to create an
        //array indexed by the group ID with a value of the number of files with
        //that same ID. Make sense?
        $duplicate_count_array = [];

        //Cache just the file size for the give duplicate group id
        $size_array = [];

        $io->text( 'Grouping duplicates' );

        //Weird PHP League 8.0 way of counting all the records. Totally a
        //performance killer because we're doing things twice but needed for the
        //progress bar and come on, who doesn't want a progress bar!
        $count = $reader->each(
                            function()
                            {
                                return true;
                            }
                        );

        //Start the progress bar with our record count
        $io->progressStart( $count );

        //This just holds the current loop's index count for reference
        $idx = 0;
        foreach( $results as $row )
        {

            //Get this field, store as int
            $duplicate_group = (int)$row[ 'Duplicate Group' ];

            //Add to array if it doesn't already exist with a zero value
            if( ! array_key_exists( $duplicate_group, $duplicate_count_array ) )
            {
                $duplicate_count_array[ $duplicate_group ] = 0;
            }

            //Increment the array
            $duplicate_count_array[ $duplicate_group ]++;

            //We don't need an if-check on this one although it will overwrite
            //existing keys. However, same keys have the same value (since they
            //represent duplicate file sizes) so that's OK. If-checking here
            //would actually lead to a perf problem.
            $size_array[ $duplicate_group ] = (int)$row[ 'Size(Byte)' ];

            //More weird progress bar stuff. Perform a MOD zero check and
            //advance the progress bar every once in a while.
            if( 0 === ( ++$idx ) % $progress_bar_every )
            {
                $io->progressAdvance( $progress_bar_every );
            }
        }

        //Finish the progress bar.
        $io->progressFinish();

        //Okay the arrays now look something like this:
        //$duplicate_count_array = [
        //                              1 => 1, //Not a duplicate
        //                              2 => 1, //Not a duplicate
        //                              3 => 2, //Duplicate
        //                              4 => 1, //Not a duplicate
        //      ];
        //$size_array            = [
        //                              1 => 1234,
        //                              2 => 984523,
        //                              3 => 398754,
        //                              4 => 579274,
        //      ];
        //
        //We're going to loop through $duplicate_count_array looking for all
        //file with more than one duplicate. For those files, we're going to
        //grab the corresponding entry from the size array (they are indexed
        //the same and guaranteed to match) and multiple that by "one less than
        //the number of duplicates" which gives us our wasted space. So if you
        //have three duplicate files, one needs to exist and the other two are
        //technically the duplicates. Hmmm... maybe duplicate wasn't the best
        //name for the initial variable. Bah... that's for another day.
        //
        //This array doesn't actually need to exist but I like to do things in
        //steps. This will hold one entry for each wasted space for a duplicate
        //file group (the one minus thing from above). The index of this array
        //is undefined.
        $true_dupe_array = [];
        foreach( $duplicate_count_array as $duplicate_group => $count )
        {
            if( $count <= 1 )
            {
                continue;
            }

            $true_dupe_array[ ] = $size_array[ $duplicate_group ] * ( $count - 1 );
        }

        //Okay, now just count the above. See, I told you we could have done
        //this with one less array but I didn't want to.
        $total_sum = 0;
        foreach( $true_dupe_array as $duplicate_group => $size )
        {
            $total_sum += $size;
        }

        //Output. Success? Why not!
        $io->success(
                        sprintf(
                                    'You have %1$s tied up in %2$s files',
                                    $this->formatBytes( $total_sum ),
                                    number_format( count( $true_dupe_array ) )
                                )
                    );
    }
}
