<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Controller\Action;

use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PickupPointByIdAction
{
    private ViewHandlerInterface $viewHandler;

    private DataTransformerInterface $pickupPointToIdentifierTransformer;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        DataTransformerInterface $pickupPointToIdentifierTransformer
    ) {
        $this->viewHandler = $viewHandler;
        $this->pickupPointToIdentifierTransformer = $pickupPointToIdentifierTransformer;
    }

    public function __invoke(Request $request): Response
    {
        $pickupPointId = $request->get('pickupPointId');
        if (!is_scalar($pickupPointId) || '' === $pickupPointId) {
            throw new NotFoundHttpException();
        }

        /** @var PickupPointInterface|mixed $pickupPoint */
        $pickupPoint = $this->pickupPointToIdentifierTransformer->reverseTransform($pickupPointId);
        if (!$pickupPoint instanceof PickupPointInterface) {
            throw new NotFoundHttpException();
        }

        $view = View::create($pickupPoint);
        $view->getContext()->addGroup('Detailed');

        return $this->viewHandler->handle($view);
    }
}
