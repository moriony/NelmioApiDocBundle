<?php

/*
 * This file is part of the NelmioApiDocBundle.
 *
 * (c) Nelmio <hello@nelm.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nelmio\ApiDocBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class DumpCommand extends ContainerAwareCommand
{
    /**
     * @var array
     */
    protected $availableFormats = array('markdown', 'json', 'html');

    protected function configure()
    {
        $this
            ->setDescription('')
            ->addOption(
                'format', '', InputOption::VALUE_REQUIRED,
                'Output format like: ' . implode(', ', $this->availableFormats),
                $this->availableFormats[0]
            )
            ->addOption('no-sandbox', '', InputOption::VALUE_NONE)
            ->addOption('api-version', null, InputOption::VALUE_REQUIRED, 'The API version')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Locale for translation')
            ->setName('api:doc:dump')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $format = $input->getOption('format');
        $routeCollection = $this->getContainer()->get('router')->getRouteCollection();

        if (!$input->hasOption('format') || in_array($format, array('json'))) {
            $formatter = $this->getContainer()->get('nelmio_api_doc.formatter.simple_formatter');
        } else {
            if (!in_array($format, $this->availableFormats)) {
                throw new \RuntimeException(sprintf('Format "%s" not supported.', $format));
            }

            $formatter = $this->getContainer()->get(sprintf('nelmio_api_doc.formatter.%s_formatter', $format));
        }

        if ($input->hasOption('locale')) {
            $this->getContainer()->get('translator')->setLocale($input->getOption('locale'));
        }

        if ($input->getOption('no-sandbox') && 'html' === $format) {
            $formatter->setEnableSandbox(false);
        }

        if ('html' === $format) {
            $this->getContainer()->enterScope('request');
            $this->getContainer()->set('request', new Request(), 'request');
        }

        $extractor = $this->getContainer()->get('nelmio_api_doc.extractor.api_doc_extractor');
        $extractedDoc = $input->hasOption('api-version') ?
            $extractor->allForVersion($input->getOption('api-version')) :
            $extractor->all();

        $formattedDoc = $formatter->format($extractedDoc);

        if ('json' === $format) {
            $output->writeln(json_encode($formattedDoc));
        } else {
            $output->writeln($formattedDoc);
        }
    }
}
