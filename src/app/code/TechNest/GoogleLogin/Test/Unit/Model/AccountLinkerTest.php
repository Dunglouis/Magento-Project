<?php
declare(strict_types=1);

namespace TechNest\GoogleLogin\Test\Unit\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TechNest\GoogleLogin\Model\AccountLinker;
use TechNest\GoogleLogin\Model\GoogleProfile;
use TechNest\GoogleLogin\Model\ResourceModel\GoogleAccount;

// Shared mocks: each test sets expectations only on the collaborators its path touches
#[AllowMockObjectsWithoutExpectations]
class AccountLinkerTest extends TestCase
{
    /** @var GoogleAccount&MockObject */
    private GoogleAccount $googleAccount;
    /** @var CustomerRepositoryInterface&MockObject */
    private CustomerRepositoryInterface $customerRepository;
    /** @var AccountManagementInterface&MockObject */
    private AccountManagementInterface $accountManagement;
    /** @var CustomerInterfaceFactory&MockObject */
    private CustomerInterfaceFactory $customerFactory;
    /** @var GoogleProfile */
    private GoogleProfile $profile;

    protected function setUp(): void
    {
        $this->googleAccount = $this->createMock(GoogleAccount::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->accountManagement = $this->createMock(AccountManagementInterface::class);
        $this->customerFactory = $this->createMock(CustomerInterfaceFactory::class);
        $this->profile = new GoogleProfile('sub-1', 'an@gmail.com', 'An', 'Nguyễn');
    }

    private function linker(): AccountLinker
    {
        return new AccountLinker(
            $this->googleAccount,
            $this->customerRepository,
            $this->customerFactory,
            $this->accountManagement
        );
    }

    private function customer(int $id): CustomerInterface
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getId')->willReturn($id);
        return $customer;
    }

    public function testLinkedGoogleAccountSignsInAsItsCustomer(): void
    {
        $customer = $this->customer(7);
        $this->googleAccount->method('getCustomerIdBySub')->with('sub-1')->willReturn(7);
        $this->customerRepository->expects($this->once())->method('getById')->with(7)->willReturn($customer);
        $this->googleAccount->expects($this->never())->method('link');

        $this->assertSame($customer, $this->linker()->resolve($this->profile, 1, 2));
    }

    public function testExistingEmailIsLinked(): void
    {
        $customer = $this->customer(5);
        $this->googleAccount->method('getCustomerIdBySub')->willReturn(null);
        $this->googleAccount->method('getSubByCustomerId')->with(5)->willReturn(null);
        $this->customerRepository->method('get')->with('an@gmail.com', 1)->willReturn($customer);
        $this->accountManagement->expects($this->never())->method('createAccount');
        $this->googleAccount->expects($this->once())->method('link')->with('sub-1', 5, 'an@gmail.com');

        $this->assertSame($customer, $this->linker()->resolve($this->profile, 1, 2));
    }

    public function testNewEmailCreatesCustomerInTheCurrentStore(): void
    {
        $draft = $this->createMock(CustomerInterface::class);
        foreach (['setEmail' => 'an@gmail.com', 'setFirstname' => 'An', 'setLastname' => 'Nguyễn',
                     'setWebsiteId' => 1, 'setStoreId' => 2] as $setter => $value) {
            $draft->expects($this->once())->method($setter)->with($value)->willReturnSelf();
        }
        $created = $this->customer(9);
        $this->googleAccount->method('getCustomerIdBySub')->willReturn(null);
        $this->googleAccount->method('getSubByCustomerId')->willReturn(null);
        $this->customerRepository->method('get')->willThrowException(new NoSuchEntityException());
        $this->customerFactory->method('create')->willReturn($draft);
        $this->accountManagement->expects($this->once())->method('createAccount')->with($draft)->willReturn($created);
        $this->googleAccount->expects($this->once())->method('link')->with('sub-1', 9, 'an@gmail.com');

        $this->assertSame($created, $this->linker()->resolve($this->profile, 1, 2));
    }

    public function testCustomerLinkedToAnotherGoogleAccountIsRefused(): void
    {
        $this->googleAccount->method('getCustomerIdBySub')->willReturn(null);
        $this->googleAccount->method('getSubByCustomerId')->willReturn('sub-other');
        $this->customerRepository->method('get')->willReturn($this->customer(5));
        $this->googleAccount->expects($this->never())->method('link');

        $this->expectException(LocalizedException::class);
        $this->linker()->resolve($this->profile, 1, 2);
    }
}
