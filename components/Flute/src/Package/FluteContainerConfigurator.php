<?php namespace Limoncello\Flute\Package;

use Doctrine\DBAL\Connection;
use Limoncello\Contracts\Application\ContainerConfiguratorInterface;
use Limoncello\Contracts\Container\ContainerInterface as LimoncelloContainerInterface;
use Limoncello\Contracts\Data\ModelSchemeInfoInterface;
use Limoncello\Contracts\Exceptions\ExceptionHandlerInterface;
use Limoncello\Contracts\Settings\SettingsProviderInterface;
use Limoncello\Flute\Adapters\FilterOperations;
use Limoncello\Flute\Adapters\PaginationStrategy;
use Limoncello\Flute\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\Flute\Contracts\Adapters\RepositoryInterface;
use Limoncello\Flute\Contracts\Encoder\EncoderInterface;
use Limoncello\Flute\Contracts\FactoryInterface;
use Limoncello\Flute\Contracts\I18n\TranslatorInterface;
use Limoncello\Flute\Contracts\Schema\JsonSchemesInterface;
use Limoncello\Flute\Factory;
use Limoncello\Flute\Http\Errors\FluteExceptionHandler;
use Limoncello\Validation\Contracts\TranslatorInterface as ValidationTranslatorInterface;
use Limoncello\Validation\I18n\Locales\EnUsLocale;
use Limoncello\Validation\I18n\Translator;
use Neomerx\JsonApi\Contracts\Http\Query\QueryParametersParserInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;
use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * @package Limoncello\Flute
 */
class FluteContainerConfigurator implements ContainerConfiguratorInterface
{
    /** @var callable */
    const HANDLER = [self::class, self::METHOD_NAME];

    /** @var callable */
    const CONFIGURE_EXCEPTION_HANDLER = [self::class, 'configureExceptionHandler'];

    /**
     * @inheritdoc
     */
    public static function configure(LimoncelloContainerInterface $container)
    {
        $factory = new Factory($container);

        $container[FactoryInterface::class] = function () use ($factory) {
            return $factory;
        };

        $container[QueryParametersParserInterface::class] = function () use ($factory) {
            return $factory->getJsonApiFactory()->createQueryParametersParser();
        };

        $container[JsonSchemesInterface::class] = function (PsrContainerInterface $container) use ($factory) {
            $settings     = $container->get(SettingsProviderInterface::class)->get(FluteSettings::class);
            $modelSchemes = $container->get(ModelSchemeInfoInterface::class);

            return $factory->createJsonSchemes($settings[FluteSettings::KEY_MODEL_TO_SCHEME_MAP], $modelSchemes);
        };

        $container[EncoderInterface::class] = function (PsrContainerInterface $container) use ($factory) {
            /** @var JsonSchemesInterface $jsonSchemes */
            $jsonSchemes = $container->get(JsonSchemesInterface::class);
            $settings    = $container->get(SettingsProviderInterface::class)->get(FluteSettings::class);
            $encoder     = $factory->createEncoder($jsonSchemes, new EncoderOptions(
                $settings[FluteSettings::KEY_JSON_ENCODE_OPTIONS],
                $settings[FluteSettings::KEY_URI_PREFIX],
                $settings[FluteSettings::KEY_JSON_ENCODE_DEPTH]
            ));
            if (isset($settings[FluteSettings::KEY_META])) {
                $encoder->withMeta($settings[FluteSettings::KEY_META]);
            }
            if ($settings[FluteSettings::KEY_IS_SHOW_VERSION] ?? false) {
                $encoder->withJsonApiVersion();
            }

            return $encoder;
        };

        $container[TranslatorInterface::class] = $translator = $factory->createTranslator();

        $container[ValidationTranslatorInterface::class] = function () {
            // TODO load locale according to current user preferences
            return new Translator(EnUsLocale::getLocaleCode(), EnUsLocale::getMessages());
        };

        $container[RepositoryInterface::class] = function (PsrContainerInterface $container) use ($factory, $translator) {
            $connection       = $container->get(Connection::class);
            $filterOperations = new FilterOperations($translator);
            /** @var ModelSchemeInfoInterface $modelSchemes */
            $modelSchemes     = $container->get(ModelSchemeInfoInterface::class);

            return $factory->createRepository($connection, $modelSchemes, $filterOperations, $translator);
        };

        $container[PaginationStrategyInterface::class] = function (PsrContainerInterface $container) {
            $settings = $container->get(SettingsProviderInterface::class)->get(FluteSettings::class);

            return new PaginationStrategy($settings[FluteSettings::KEY_RELATIONSHIP_PAGING_SIZE]);
        };
    }

    /**
     * @param LimoncelloContainerInterface $container
     *
     * @return void
     */
    public static function configureExceptionHandler(LimoncelloContainerInterface $container)
    {
        $container[ExceptionHandlerInterface::class] = function () {
            return new FluteExceptionHandler();
        };
    }
}
