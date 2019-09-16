<?php

/**
 * @file
 * Tests covering AbstractSinglePathProcessingTask.
 */

declare(strict_types = 1);

use GrumPHP\Collection\ProcessArgumentsCollection;
use GrumPHP\Configuration\GrumPHP;
use GrumPHP\Formatter\ProcessFormatterInterface;
use GrumPHP\Process\ProcessBuilder;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\RunContext;
use GrumPHP\Task\TaskInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Wunderio\GrumPHP\Task\AbstractSinglePathProcessingTask;

/**
 * Class AbstractSinglePathProcessingTaskTest.
 */
final class AbstractSinglePathProcessingTaskTest extends TestCase {

  /**
   * GrumPHP object mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $grumPHP;

  /**
   * ProcessBuilder object mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $processBuilder;

  /**
   * ProcessFormatterInterface object mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $processFormatterInterface;

  /**
   * AbstractPerPathExternalTask object mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $stub;

  /**
   * Process object mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $process;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    $this->grumPHP = $this->createMock(GrumPHP::class);
    $this->processBuilder = $this->createMock(ProcessBuilder::class);
    $this->processFormatterInterface = $this->createMock(ProcessFormatterInterface::class);
    $this->stub = $this->getMockBuilder(AbstractSinglePathProcessingTask::class)
      ->setConstructorArgs([
        $this->grumPHP,
        $this->processBuilder,
        $this->processFormatterInterface,
      ])
      ->setMethodsExcept(['run'])
      ->getMockForAbstractClass();
    $this->process = $this->createMock(Process::class);
  }

  /**
   * Test run in scenario where no files or directories found.
   *
   * @covers \Wunderio\GrumPHP\Task\AbstractSinglePathProcessingTask::run
   */
  public function testSkipsTaskIfNoFilesFound(): void {
    $this->stub->expects($this->once())
      ->method('getFilesOrResult')
      ->willReturn(TaskResult::createSkipped(
        $this->createMock(TaskInterface::class),
        $this->createMock(ContextInterface::class)
      ));

    $this->processBuilder->expects($this->never())->method('buildProcess');

    $actual = $this->stub->run($this->createMock(RunContext::class));
    $this->assertInstanceOf(TaskResultInterface::class, $actual);
    $this->assertEquals(TaskResult::SKIPPED, $actual->getResultCode());
  }

  /**
   * Test run in scenario with one files found and process successful.
   *
   * @covers \Wunderio\GrumPHP\Task\AbstractSinglePathProcessingTask::run
   */
  public function testPassesTaskIfFileFoundAndProcessSuccessful(): void {
    $this->stub->expects($this->once())->method('getFilesOrResult')->willReturn(['file.php']);
    $this->stub->expects($this->once())
      ->method('buildArgumentsFromPath')
      ->willReturn($this->createMock(ProcessArgumentsCollection::class));
    $this->processBuilder->expects($this->once())->method('buildProcess')->willReturn($this->process);
    $this->process->expects($this->once())->method('run');
    $this->process->expects($this->once())->method('isSuccessful')->willReturn(TRUE);

    $actual = $this->stub->run($this->createMock(RunContext::class));
    $this->assertInstanceOf(TaskResultInterface::class, $actual);
    $this->assertTrue($actual->isPassed());
  }

  /**
   * Test run in scenario with multiple found and process unsuccessful.
   *
   * @covers \Wunderio\GrumPHP\Task\AbstractSinglePathProcessingTask::run
   */
  public function testFailsTaskIfMultipleFilesFoundButProcessUnsuccessful(): void {
    $this->stub->expects($this->once())->method('getFilesOrResult')->willReturn(
      ['file.php', 'directory/']
    );
    $this->stub->expects($this->exactly(2))
      ->method('buildArgumentsFromPath')
      ->willReturn($this->createMock(ProcessArgumentsCollection::class));
    $this->processBuilder->expects($this->exactly(2))->method('buildProcess')->willReturn($this->process);
    $this->process->expects($this->exactly(2))->method('run');
    $this->process->expects($this->exactly(2))->method('isSuccessful')->willReturn(FALSE);
    $this->processFormatterInterface->expects($this->exactly(2))->method('format')->willReturn('Error');

    $actual = $this->stub->run($this->createMock(RunContext::class));
    $this->assertInstanceOf(TaskResultInterface::class, $actual);
    $this->assertFalse($actual->isPassed());
  }

}