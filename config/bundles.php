<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineCacheBundle\DoctrineCacheBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => ['all' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
    FOS\UserBundle\FOSUserBundle::class => ['all' => true],
    EMS\CoreBundle\EMSCoreBundle::class => ['all' => true],
    EMS\CommonBundle\EMSCommonBundle::class => ['all' => true],
    EMS\MakerBundle\EMSMakerBundle::class => ['all' => true],
    Symplify\ConsoleColorDiff\ConsoleColorDiffBundle::class => ['all' => true],
    Symplify\ComposerJsonManipulator\ComposerJsonManipulatorBundle::class => ['all' => true],
    EMS\ClientHelperBundle\EMSClientHelperBundle::class => ['all' => true],
];
