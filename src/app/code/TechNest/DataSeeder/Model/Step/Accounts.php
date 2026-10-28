<?php
declare(strict_types=1);

namespace TechNest\DataSeeder\Model\Step;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TechNest\DataSeeder\Model\SeedData;
use TechNest\DataSeeder\Model\StepInterface;

/**
 * Test/demo customer accounts from data/accounts.json, with a default address and phone number.
 * Passwords never live in the repo: each account names an environment variable (env/phpfpm.env);
 * accounts whose variable is empty are skipped. Re-runs reset the password and keep the rest.
 */
class Accounts implements StepInterface
{
    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * @param SeedData $seedData
     * @param StoreManagerInterface $storeManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerInterfaceFactory $customerFactory
     * @param AddressInterfaceFactory $addressFactory
     * @param RegionInterfaceFactory $regionFactory
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly SeedData $seedData,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly RegionInterfaceFactory $regionFactory,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return 'Test customer accounts (passwords from environment variables)';
    }

    /**
     * @inheritdoc
     */
    public function execute(OutputInterface $output): void
    {
        foreach ($this->seedData->getAccounts() as $entry) {
            // CLI-only seeding: the secret comes from the container environment on purpose, not from config
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            $password = $this->password(getenv($entry['password_env']));
            if ($password === null) {
                $output->writeln(sprintf(
                    '  <comment>skip %s: set %s (at least %d characters) in env/phpfpm.env</comment>',
                    $entry['email'],
                    $entry['password_env'],
                    self::MIN_PASSWORD_LENGTH
                ));
                continue;
            }
            $store = $this->storeManager->getStore($entry['store']);
            $customer = $this->load($entry['email'], (int)$store->getWebsiteId());
            $isNew = !$customer->getId();
            $customer->setEmail($entry['email'])
                ->setFirstname($entry['firstname'])
                ->setLastname($entry['lastname'])
                ->setWebsiteId((int)$store->getWebsiteId())
                ->setStoreId((int)$store->getId())
                ->setCustomAttribute('phone_number', $entry['phone_number']);
            if (!$customer->getAddresses()) {
                $customer->setAddresses([$this->address($entry)]);
            }
            $this->customerRepository->save($customer, $this->encryptor->getHash($password, true));
            $output->writeln(sprintf('  %s %s', $isNew ? 'created' : 'updated', $entry['email']));
        }
    }

    /**
     * Usable password from the environment value, or null when missing/too short
     *
     * @param string|false $value getenv() result
     * @return string|null
     */
    public function password(string|false $value): ?string
    {
        $value = $value === false ? '' : trim($value);
        return strlen($value) >= self::MIN_PASSWORD_LENGTH ? $value : null;
    }

    /**
     * Existing customer for the email, or a new one
     *
     * @param string $email
     * @param int $websiteId
     * @return CustomerInterface
     */
    private function load(string $email, int $websiteId): CustomerInterface
    {
        try {
            return $this->customerRepository->get($email, $websiteId);
        } catch (NoSuchEntityException) {
            return $this->customerFactory->create();
        }
    }

    /**
     * Default billing + shipping address
     *
     * @param array<string,mixed> $entry
     * @return \Magento\Customer\Api\Data\AddressInterface
     */
    private function address(array $entry): \Magento\Customer\Api\Data\AddressInterface
    {
        $data = $entry['address'];
        return $this->addressFactory->create()
            ->setFirstname($entry['firstname'])
            ->setLastname($entry['lastname'])
            ->setStreet($data['street'])
            ->setCity($data['city'])
            ->setRegion($this->regionFactory->create()->setRegion($data['region']))
            ->setPostcode($data['postcode'])
            ->setCountryId($data['country_id'])
            ->setTelephone($entry['phone_number'])
            ->setIsDefaultBilling(true)
            ->setIsDefaultShipping(true);
    }
}
