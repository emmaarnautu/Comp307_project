
<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/session.php';

class SessionTest extends TestCase
{
    // CREATE TICKET TESTS
    // Clean up the mocked database connection after each test
    protected function tearDown(): void
    {
        unset($GLOBALS['pdo']);
    }

    // Create a fake database connection so tests don't modify MySQL
    private function mockDatabase(): PDO
    {
        // Mock the SQL statement and simulate a successful execution
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        // Mock PDO and make prepare() return our fake statement
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        // Replace the global database connection with the mock
        $GLOBALS['pdo'] = $pdo;

        return $pdo;
    }

    // Check that the generated ticket contains exactly 64 characters
    public function testTicketHasCorrectLength(): void
    {
        $this->mockDatabase();

        $ticket = create_ticket(1);

        $this->assertEquals(64, strlen($ticket));
    }

    // Check that the ticket contains only hexadecimal characters
    public function testTicketIsHexadecimal(): void
    {
        $this->mockDatabase();

        $ticket = create_ticket(1);

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $ticket
        );
    }

    // Check that two tickets generated for the same user are different
    public function testTicketsAreUnique(): void
    {
        $this->mockDatabase();

        $ticket1 = create_ticket(1);
        $ticket2 = create_ticket(1);

        $this->assertNotEquals($ticket1, $ticket2);
    }

    // Check that MySQL receives the hashed ticket, not the original
    public function testTicketIsHashedBeforeStorage(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $pdo = $this->createMock(PDO::class);

        $pdo->method('prepare')->willReturn($stmt);
        $GLOBALS['pdo'] = $pdo;

        $savedValues = [];

        // Capture the values passed to execute()
        $stmt->expects($this->once())
            ->method('execute')
            ->willReturnCallback(
                function ($values) use (&$savedValues) {
                    $savedValues = $values;
                    return true;
                }
            );

        $ticket = create_ticket(42);

        // Verify that the correct user ID was passed
        $this->assertSame(42, $savedValues[0]);

        // Verify that the stored value matches the SHA-256 hash
        $this->assertSame(
            hash('sha256', $ticket),
            $savedValues[1]
        );

        // Verify that the original ticket was not stored
        $this->assertNotSame(
            $ticket,
            $savedValues[1]
        );
    }

    //--------------------------------------------
}
