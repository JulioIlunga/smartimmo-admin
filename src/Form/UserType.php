<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\PasswordRequirements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phone', NumberType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'required' => true,
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'attr' => [
                    'class' => 'form-control border-0 px-0'
                ],
                'first_options' => [
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Veuillez entrer un mot de passe',
                        ]),
                        new PasswordRequirements()
                    ],
                    'label' => 'Nouveau mot de passe',
                ],
                'second_options' => [
                    'label' => 'Répéter le mot de passe',
                ],
                'invalid_message' => 'Le mot de passe ne correspond pas',
                // Instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('phonecode', ChoiceType::class, [
                'choices'  => [
                    'RDC (+243)' => '+243',
                    'Congo (+242)' => '+242',
                    'USA (+1)' => '+1',
                    'France (+33)' => '+33',
                    'Belgium (+32)' => '+32',
                    'Angola (+244)' =>	'+244',
                    'Australia (+61)' =>	'+61',
                    'Bangladesh (+880)' =>	'+880',
                    'Benin (+299)' => '+229',
                    'Brazil'=> '+55',
                    'Burkina Faso (+226)' => '+226',
                    'Burundi (+257)'=>	'+257',
                    'Cameroon (+237)'=> '+237',
                    'Canada (+1)'=>	'+1',
                    'Central African Republic (+236)'=>	'+236',
                    'Chad (+235)' => '+235',
                    'China (+86)' =>	'+86',
                    'Comoros (+269)' =>	'+269',
                    'Denmark (+45)' =>	'+45',
                    'Egypt (+20)' => '+20',
                    'Equatorial Guinea (+240)'	=>'+240',
                    'Ethiopia (+251)'	=>'+251',
                    'Finland (+358)'=>	'+358',
                    'Gabon (+241)'=>	'+241',
                    'Germany (+49)'=>	'+49',
                    'Ghana (+233)'=>	'+233',
                    'Greece (+30)'=>	'+30',
                    'India (+91)'=>	'+91',
                    'Ireland (+353)'=>	'+353',
                    'Italy (+39)'=>	'+39',
                    'Japan (+81)'=>	'+81',
                    'Kenya (+254)'=>	'+254',
                    'Liberia (+231)'=>	'+231',
                    'Libya (+218)'	=>'+218',
                    'Malawi (+265)'	=>'+265',
                    'Malaysia (+60)'=>	'+60',
                    'Maldives (+960)'=>	'+960',
                    'Mali (+223)'=>	'+223',
                    'Mauritius (+230)'	=>'+230',
                    'Mexico (+52)'	=>'+52',
                    'Monaco (+377)'	=>'+377',
                    'Mongolia (+976)'=>	'+976',
                    'Montenegro (+382)'	=>'+382',
                    'Morocco (+212)' =>'+212',
                    'Mozambique (+258)'=> '+258',
                    'Namibia (+264)'=>	'+264',
                    'Netherlands (+31)'	=>'+31',
                    'New Zealand (+64)'	=>'+64',
                    'Niger (+227)'	=>'+227',
                    'Nigeria (+234)' =>'+234',
                    'Panama (+507)'	=>'+507',
                    'Poland (+48)' =>'+48',
                    'Portugal (+351)' =>'+351',
                    'Qatar (+974)' =>'+974',
                    'Romania (+40)'	=>'+40',
                    'Russia (+7)' =>'+7',
                    'Rwanda (+250)'	=>'+250',
                    'Senegal (+221)'=>	'+221',
                    'Singapore (+65)'=>	'+65',
                    'South Africa (+27)'=>	'+27',
                    'Spain (+34)' =>'+34',
                    'Switzerland (+41)'	=>'+41',
                    'Tanzania (+255)' =>'+255',
                    'Thailand (+66)'=> '+66',
                    'Turkey (+90)'=> '+90',
                    'Uganda (+256)'=> '+256',
                    'United Arab Emirates (+971)'=>	'+971',
                    'United Kingdom (+44)'=> '+44',
                    'United States (+1)'=> '+1',
                    'Zambia (+260)'	=>'+260',
                    'Zimbabwe (+263)' =>'+263',
                ],
                'required' => true,
            ])
//            ->add('userType', ChoiceType::class, [
//                'constraints' => [
//                    new NotBlank(),
//                ],
//                'choices'  => [
//                    'Agent immobilier (Proposer des biens à vendre ou louer)' => '2',
//                    'Client (Trouver ma prochaine maison)' => '1',
//                ],
//                'required' => true,
//            ])
            ->add('userType', ChoiceType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'choices'  => [
                    'Client (Trouver ma prochaine maison)' => '1',
                    'Agent immobilier (Proposer des biens à vendre ou louer)' => '2',
                ],
                'required' => true,
                'expanded' => true, // to display as radio buttons
                'multiple' => false,
                'data' => '1', // this sets the first option as the default selected
            ])
            ->add('firstname', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'required' => true,
            ])
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'required' => true,
            ])
            ->add('termsAndCondition', CheckboxType::class, [
                'constraints' => [
                    new NotBlank(),
                ],
                'attr'=>[
                    'class' => 'form-check-input'
                ],
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
