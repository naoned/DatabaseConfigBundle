<?php

namespace Egzakt\DatabaseConfigBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

use Egzakt\DatabaseConfigBundle\Entity\Extension;
use Egzakt\DatabaseConfigBundle\Form\ConfiguratorType;

/**
 * Locale Controller
 */
class ConfiguratorController extends Controller
{

    /**
     * @return Response
     */
    public function indexAction()
    {
        return $this->render('EgzaktDatabaseConfigBundle::index.html.twig', array(
            'bundles' => $this->getConfiguratorEnabledBundles()
        ));
    }

    /**
     * Display a form to edit the configuration of a bundle
     *
     * @param Request $request
     * @param string $bundleName
     *
     * @return Response
     */
    public function editAction(Request $request, $bundleName)
    {
        $manager = $this->getDoctrine()->getManager();
        $extensionRepository = $this->getDoctrine()->getRepository('EgzaktDatabaseConfigBundle:Extension');
        $configRepository = $this->getDoctrine()->getRepository('EgzaktDatabaseConfigBundle:Config');

        $tree = $this->getConfigNode($bundleName);
        $extension = $extensionRepository->findOneByName($tree->getName());

        if (false == $extension) {
            $extension = new Extension();
            $extension->setName($tree->getName());
        }

        $form = $this->createForm(new ConfiguratorType(), $extension, array('tree' => $tree, 'request' => $request));

        if ('POST' == $request->getMethod()) {

            $form->bind($request);

            if ($form->isValid()) {

                // removing the previous config entries from the database
                $configRepository->deleteByExtension($extension->getId());

                $manager->persist($extension);
                $manager->flush($extension);

                $this->get('egzakt_database_config.container_invalidator')->invalidate();
            }
        }

        return $this->render('EgzaktDatabaseConfigBundle::edit.html.twig', array(
            'form' => $form->createView(),
            'bundles' => $this->getConfiguratorEnabledBundles()
        ));
    }

    /**
     * @param string $bundleName
     *
     * @return NodeInterface
     */
    private function getConfigNode($bundleName)
    {
        $bundles = $this->get('kernel')->getBundles();
        $bundle = $bundles[$bundleName];
        $extension = $bundle->getContainerExtension();

        if ($extension) {
            $configuration = $extension->getConfiguration(array(), new ContainerBuilder());
            $tree = $configuration->getConfigTreeBuilder()->buildTree();

            return $tree;
        }
    }

    /**
     * Check each bundle currently loaded in the kernel and validate configurator support
     *
     * -
     *
     * @return array
     */
    private function getConfiguratorEnabledBundles()
    {
        $enabledBundles = array();
        $bundles = $this->get('kernel')->getBundles();

        foreach ($bundles as $name => $bundle) {
            try {
                if ($tree = $this->getConfigurationTree($bundle)) {
                    if ($tree && $this->isConfiguratorEnabledNode($tree)) {
                        $enabledBundles[] = $name;
                    }
                }
            } catch (\Exception $e) {
                // skip error'd bundle
            }
        }

        return $enabledBundles;
    }

    private function getConfigurationTree(BundleInterface $bundle)
    {
        $extension = $bundle->getContainerExtension();

        if ($extension) {
            $configuration = $extension->getConfiguration(array(), new ContainerBuilder());
            if ($configuration) {
                return $configuration->getConfigTreeBuilder()->buildTree();
            }
        }
    }

    /**
     * Check if a configuration node is configuration enabled
     *
     * @param NodeInterface $arrayNode
     *
     * @return bool
     */
    private function isConfiguratorEnabledNode(NodeInterface $arrayNode)
    {
        foreach ($arrayNode->getChildren() as $node) {
            if ($node->getAttribute('configurator')) {
                return true;
            } elseif ($node instanceof ArrayNode) {
                return $this->isConfiguratorEnabledNode($node);
            }
        }
    }


}
