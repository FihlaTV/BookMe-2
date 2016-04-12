<?php
namespace IComeFromTheNet\BookMe;

use DateTime;
use Pimple\Container;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use League\Tactician\CommandBus;
use League\Tactician\Handler\Locator;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;
use League\Tactician\Plugins\LockingMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\CommandEvents\EventMiddleware;
use League\Tactician\CommandEvents\Event\CommandHandled;
use Bezdomni\Tactician\Pimple\PimpleLocator;

use IComeFromTheNet\BookMe\Bus\Command\CalAddYearCommand;
use IComeFromTheNet\BookMe\Bus\Command\SlotAddCommand;
use IComeFromTheNet\BookMe\Bus\Command\SlotToggleStatusCommand;
use IComeFromTheNet\BookMe\Bus\Command\RegisterMemberCommand;
use IComeFromTheNet\BookMe\Bus\Command\RegisterTeamCommand;
use IComeFromTheNet\BookMe\Bus\Command\RolloverTeamsCommand;
use IComeFromTheNet\BookMe\Bus\Command\RolloverSchedulesCommand;
use IComeFromTheNet\BookMe\Bus\Command\TakeBookingCommand;
use IComeFromTheNet\BookMe\Bus\Command\ClearBookingCommand;




use IComeFromTheNet\BookMe\Bus\Handler\CalAddYearHandler;
use IComeFromTheNet\BookMe\Bus\Handler\SlotAddHandler;
use IComeFromTheNet\BookMe\Bus\Handler\SlotToggleStatusHandler;
use IComeFromTheNet\BookMe\Bus\Handler\RegisterMemberHandler;
use IComeFromTheNet\BookMe\Bus\Handler\RegisterTeamHandler;
use IComeFromTheNet\BookMe\Bus\Handler\RolloverTeamsHandler;
use IComeFromTheNet\BookMe\Bus\Handler\RolloverSchedulesHandler;
use IComeFromTheNet\BookMe\Bus\Handler\TakeBookingHandler;
use IComeFromTheNet\BookMe\Bus\Handler\ClearBookingHandler;

use IComeFromTheNet\BookMe\Bus\Listener\CommandHandled as CustomHandler;

use IComeFromTheNet\BookMe\Bus\Middleware\ValidatePropMiddleware;
use IComeFromTheNet\BookMe\Bus\Middleware\ExceptionWrapperMiddleware;
use IComeFromTheNet\BookMe\Bus\Middleware\UnitOfWorkMiddleware;



/**
 * Book Me DI Container
 * 
 * Your Database Admin must allow user variables for this code to function.
 *
 * @author Lewis Dyer <getintouch@icomefromthenet.com>
 * @since 1.0
 */
class BookMeContainer extends Container
{
    
    
    
    
    

    public function __construct(Connection $dbal,LoggerInterface $logger,EventDispatcherInterface $dispatcher, DateTime $oNow)
    {
        $this['database'] = $dbal;
        $this['logger']   = $logger;
        $this['eventDispatcher'] = $dispatcher;
        $this['booted'] = false;
        $this['now'] = $oNow;
        
    }
    
    /**
     * Gets the Service ready for operation.
     * 
     * 1. Build Dependency Graph
     * 2. Wire up event handlers
     * 
     * Will only boot once.
     * 
     * @return $this;
     * @access public
     */ 
    public function boot(array $aTableNames = array())
    {
        if(false === $this['booted']) {
        
        
            # default table name map
            $this['tableMap'] = array_merge(array(
                'bm_calendar'           => 'bm_calendar',
                'bm_calendar_weeks'     => 'bm_calendar_weeks',
                'bm_calendar_months'    => 'bm_calendar_months',
                'bm_calendar_quarters'  => 'bm_calendar_quarters',
                'bm_calendar_years'     => 'bm_calendar_years',
                
                'bm_timeslot'           => 'bm_timeslot',
                'bm_timeslot_day'       => 'bm_timeslot_day',
                
                'bm_schedule_membership'   => 'bm_schedule_membership',
                'bm_schedule_team'         => 'bm_schedule_team',
                'bm_schedule_team_members' => 'bm_schedule_team_members',
                'bm_schedule'              => 'bm_schedule',
                
                'bm_booking'               => 'bm_booking',
                'bm_booking_conflict'      => 'bm_booking_conflict',
                
                
            ),$aTableNames);
        
        
            $this['commandBus.handler'] = function($c) {
                return new CustomHandler($c->getEventDispatcher());
            };
        
        
            # Command Bus Handlers
            
            $this['handlers.cal.addyear'] = function($c) {
                return new  CalAddYearHandler($c->getTableMap(), $c->getDatabaseAdapter()); 
                
            };
            
            $this['handlers.slot.add'] = function($c) {
                return new SlotAddHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
            
            $this['handlers.slot.toggle'] = function($c) {
                return new SlotToggleStatusHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
            
            $this['handlers.member.register'] = function($c) {
                return new RegisterMemberHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
          
            $this['handlers.team.register'] = function($c) {
                return new RegisterTeamHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
            
            $this['handlers.team.rollover'] = function($c) {
                return new RolloverTeamsHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
            
            $this['handlers.schedule.rollover'] = function($c) {
                return new RolloverSchedulesHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
            
            $this['handlers.booking.take'] = function($c) {
                return new TakeBookingHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
            
            $this['handlers.schedule.clear'] = function($c) {
                return new ClearBookingHandler($c->getTableMap(), $c->getDatabaseAdapter());  
            };
            
            
            # Command Bus
            
            $this['commandBus'] = function($c){
                
                $aLocatorMap = [
                    CalAddYearCommand::class        => 'handlers.cal.addyear',
                    SlotAddCommand::class           => 'handlers.slot.add',
                    SlotToggleStatusCommand::class  => 'handlers.slot.toggle',
                    RegisterMemberCommand::class    => 'handlers.member.register',
                    RegisterTeamCommand::class      => 'handlers.team.register',
                    RolloverSchedulesCommand::class => 'handlers.schedule.rollover',
                    RolloverTeamsCommand::class     => 'handlers.team.rollover',
                    TakeBookingCommand::class       => 'handlers.booking.take',
                    ClearBookingCommand::class      => 'handlers.schedule.clear',
                ];
        
             
                // Create the Middleware that loads the commands
             
                $oCommandNamingExtractor = new ClassNameExtractor();
                $oCommandLoadingLocator  = new PimpleLocator($c, $aLocatorMap);
                $oCommandNameInflector   = new HandleInflector();
                    
                $oCommandMiddleware      = new CommandHandlerMiddleware($oCommandNamingExtractor,$oCommandLoadingLocator,$oCommandNameInflector);
                
                // Create exrta Middleware 
 
                $oEventMiddleware       = new EventMiddleware();
                $oEventMiddleware->addListener(
                	'command.handled',
                	function (CommandHandled $event) use ($c) {
                    	$c->getBusEventHandler()->handle($event);
                	}
                );
                
                
                $oLockingMiddleware     = new LockingMiddleware();
                $oValdiationMiddleware  = new ValidatePropMiddleware();
                $oExceptionMiddleware   = new ExceptionWrapperMiddleware();
                $oUnitOfWorkMiddleware  = new UnitOfWorkMiddleware($c->getDatabaseAdapter());
        
                // create the command bus
        
                $oCommandBus = new CommandBus([
                            $oExceptionMiddleware,
                            $oEventMiddleware,
                            $oLockingMiddleware,
                            $oUnitOfWorkMiddleware,
                            $oValdiationMiddleware,
                            $oCommandMiddleware
                ]);
                
                return $oCommandBus;
                
            };
            
        }
        
        
        return $this;
        
    }
    
    
    
    

    
    
    
    //-------------------------------------------------------------------
    # Internal Services
    
    
    /**
     * Returns this command bus
     * 
     * @return League\Tactician\CommandBus
     */ 
    public function getCommandBus()
    {
        return $this['commandBus'];
    }
    
    
    public function getTableMap()
    {
        return $this['tableMap'];
    }
    
    /**
     * Return the custom event bus listener
     * 
     * @return IComeFromTheNet\BookMe\Bus\Listener\CommandHandled
     */ 
    public function getBusEventHandler()
    {
        return $this['commandBus.handler'];
    }
    
    //--------------------------------------------------------------------
    # External Dependecies
    
    /**
     * Loads the doctrine database
     *
     * @return Doctrine\DBAL\Connection
     */
    public function getDatabase()
    {
        return $this['database'];
    }
    
    /**
     * Loads the doctrine database
     *
     * @return Doctrine\DBAL\Connection
     */
    public function getDatabaseAdapter()
    {
        return $this['database'];
    }
    
    
    /**
     * Loads the application log
     *
     * @return Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this['logger'];
    }
    
    /**
     * Loads the application log
     *
     * @return Symfony\Component\EventDispatcher\EventDispatcherInterface;
     */
    public function getEventDispatcher()
    {
        return $this['eventDispatcher'];
    }
    
    /**
     * Return the assigned processing date ie NOW()
     * 
     * return DateTime
     */ 
    public function getNow()
    {
        return $this['now'];
    }
    
    
}
/* End of File */