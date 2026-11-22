<?php

namespace App\Tests\Functional;

use App\Entity\Vehicule;

/**
 * Confidentialite des reclamations et gestion des vehicules par leur proprietaire.
 */
class ReclamationVehiculeTest extends FunctionalTestCase
{
    public function testUtilisateurNePeutPasVoirLaReclamationDUnAutre(): void
    {
        $auteur = $this->creerUtilisateur('auteur.reclamation@test.tn');
        $curieux = $this->creerUtilisateur('curieux.reclamation@test.tn');
        $reclamation = $this->creerReclamation($auteur);
        $this->sauvegarder();
        $this->connecter($curieux);

        $this->client->request('GET', '/reclamation/voir/'.$reclamation->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testUtilisateurConsulteSesReclamations(): void
    {
        $auteur = $this->creerUtilisateur('liste.reclamation@test.tn');
        $this->creerReclamation($auteur);
        $this->sauvegarder();
        $this->connecter($auteur);

        $this->client->request('GET', '/reclamation/mes-reclamations');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Retard du conducteur', (string) $this->client->getResponse()->getContent());
    }

    public function testConducteurConsulteSesVehicules(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.vehicules@test.tn', ['ROLE_CONDUCTEUR']);
        $this->creerVehicule($conducteur, '777TU1234');
        $this->sauvegarder();
        $this->connecter($conducteur);

        $this->client->request('GET', '/vehicule/mes-vehicules');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('777TU1234', (string) $this->client->getResponse()->getContent());
    }

    public function testConducteurNePeutPasModifierLeVehiculeDUnAutre(): void
    {
        $proprietaire = $this->creerUtilisateur('proprietaire.vehicule@test.tn', ['ROLE_CONDUCTEUR']);
        $autre = $this->creerUtilisateur('autre.conducteur@test.tn', ['ROLE_CONDUCTEUR']);
        $vehicule = $this->creerVehicule($proprietaire);
        $this->sauvegarder();
        $this->connecter($autre);

        $this->client->request('GET', '/vehicule/modifier/'.$vehicule->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testSuppressionSansJetonCsrfValideEstIgnoree(): void
    {
        $conducteur = $this->creerUtilisateur('conducteur.csrf@test.tn', ['ROLE_CONDUCTEUR']);
        $vehicule = $this->creerVehicule($conducteur);
        $this->sauvegarder();
        $this->connecter($conducteur);

        $this->client->request('POST', '/vehicule/supprimer/'.$vehicule->getId(), ['_token' => 'jeton-invalide']);

        self::assertResponseRedirects('/vehicule/mes-vehicules');
        self::assertNotNull($this->relire(Vehicule::class, $vehicule->getId()));
    }
}
