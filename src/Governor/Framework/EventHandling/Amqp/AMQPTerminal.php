<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * The software is based on the Axon Framework project which is
 * licensed under the Apache 2.0 license. For more information on the Axon Framework
 * see <http://www.axonframework.org/>.
 * 
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.governor-framework.org/>.
 */

namespace Governor\Framework\EventHandling\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage as RawMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Governor\Framework\Serializer\SerializerInterface;
use Governor\Framework\EventHandling\ClusterInterface;
use Governor\Framework\EventHandling\EventBusTerminalInterface;
use Governor\Framework\UnitOfWork\CurrentUnitOfWork;
use Governor\Framework\UnitOfWork\UnitOfWorkListenerAdapter;
use Governor\Framework\UnitOfWork\UnitOfWorkInterface;

/**
 * Description of AMQPTerminal
 *
 * @author david
 */
class AMQPTerminal implements EventBusTerminalInterface, LoggerAwareInterface
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    const DEFAULT_EXCHANGE_NAME = "Governor.EventBus";

    //  private ConnectionFactory connectionFactory;
    private $exchangeName = self::DEFAULT_EXCHANGE_NAME;
    private $isTransactional = false;
    private $isDurable = true;
    //  private ListenerContainerLifecycleManager listenerContainerLifecycleManager;
    private $messageConverter;
    //  private ApplicationContext applicationContext;
    private $serializer;
    private $routingKeyResolver;
    private $waitForAck;
    private $publisherAckTimeout = 0;
    private $clusters;
    
    public function __construct(SerializerInterface $serializer,
            RoutingKeyResolverInterface $routingKeyResolver = null,
            AMQPMessageConverterInterface $messageConverter = null)
    {
        $this->serializer = $serializer;
        $this->routingKeyResolver = null === $routingKeyResolver ? new NamespaceRoutingKeyResolver()
                    : $routingKeyResolver;
        $this->messageConverter = null === $messageConverter ? new DefaultAMQPMessageConverter($this->serializer,
                $this->routingKeyResolver, $this->isDurable) : $messageConverter;
    }

    private function tryClose(AMQPChannel $channel)
    {
        try {
            $channel->close();
        } catch (\Exception $ex) {
            $this->logger->info("Unable to close channel. It might already be closed.");
        }
    }

    /**
     * Does the actual publishing of the given <code>body</code> on the given <code>channel</code>. This method can be
     * overridden to change the properties used to send a message.
     *
     * @param channel     The channel to dispatch the message on
     * @param amqpMessage The AMQPMessage describing the characteristics of the message to publish
     * @throws java.io.IOException when an error occurs while writing the message
     */
    protected function doSendMessage(AMQPChannel $channel,
            AMQPMessage $amqpMessage)
    {
        $rawMessage = new RawMessage($amqpMessage->getBody(),
                $amqpMessage->getProperties());
        
        $channel->basic_publish($rawMessage, $this->exchangeName,
                $amqpMessage->getRoutingKey(), $amqpMessage->isMandatory(),
                $amqpMessage->isImmediate());
    }

    private function tryRollback(AMQPChannel $channel)
    {
        try {
            $channel->tx_rollback();
        } catch (\Exception $ex) {
            $this->logger->debug("Unable to rollback. The underlying channel might already be closed.");
        }
    }

    /**
     * Whether this Terminal should dispatch its Events in a transaction or not. Defaults to <code>false</code>.
     * <p/>
     * If a delegate Terminal  is configured, the transaction will be committed <em>after</em> the delegate has
     * dispatched the events.
     * <p/>
     * Transactional behavior cannot be enabled if {@link #setWaitForPublisherAck(boolean)} has been set to
     * <code>true</code>.
     *
     * @param transactional whether dispatching should be transactional or not
     */
    /*   public void setTransactional(boolean transactional) {
      Assert.isTrue(!waitForAck || !transactional,
      "Cannot set transactional behavior when 'waitForServerAck' is enabled.");
      isTransactional = transactional;
      } */

    /**
     * Enables or diables the RabbitMQ specific publisher acknowledgements (confirms). When confirms are enabled, the
     * terminal will wait until the server has acknowledged the reception (or fsync to disk on persistent messages) of
     * all published messages.
     * <p/>
     * Server ACKS cannot be enabled when transactions are enabled.
     * <p/>
     * See <a href="http://www.rabbitmq.com/confirms.html">RabbitMQ Documentation</a> for more information about
     * publisher acknowledgements.
     *
     * @param waitForPublisherAck whether or not to enab;e server acknowledgements (confirms)
     */
    /*  public void setWaitForPublisherAck(boolean waitForPublisherAck) {
      Assert.isTrue(!waitForPublisherAck || !isTransactional,
      "Cannot set 'waitForPublisherAck' when using transactions.");
      this.waitForAck = waitForPublisherAck;
      } */

    /**
     * Sets the maximum amount of time (in milliseconds) the publisher may wait for the acknowledgement of published
     * messages. If not all messages have been acknowledged withing this time, the publication will throw an
     * EventPublicationFailedException.
     * <p/>
     * This setting is only used when {@link #setWaitForPublisherAck(boolean)} is set to <code>true</code>.
     *
     * @param publisherAckTimeout The number of milliseconds to wait for confirms, or 0 to wait indefinitely.
     */
    //   public void setPublisherAckTimeout(long publisherAckTimeout) {
    //       this.publisherAckTimeout = publisherAckTimeout;
    //   }

    /**
     * Sets the ConnectionFactory providing the Connections and Channels to send messages on. The SpringAMQPTerminal
     * does not cache or reuse connections. Providing a ConnectionFactory instance that caches connections will prevent
     * new connections to be opened for each invocation to {@link #publish(org.axonframework.domain.EventMessage[])}
     * <p/>
     * Defaults to an autowired Connection Factory.
     *
     * @param connectionFactory The connection factory to set
     */
    //   public void setConnectionFactory(ConnectionFactory connectionFactory) {
    //       this.connectionFactory = connectionFactory;
    //  }

    /**
     * Sets the Message Converter that creates AMQP Messages from Event Messages and vice versa. Setting this property
     * will ignore the "durable", "serializer" and "routingKeyResolver" properties, which just act as short hands to
     * create a DefaultAMQPMessageConverter instance.
     * <p/>
     * Defaults to a DefaultAMQPMessageConverter.
     *
     * @param messageConverter The message converter to convert AMQP Messages to Event Messages and vice versa.
     */
    //  public void setMessageConverter(AMQPMessageConverter messageConverter) {
    //      this.messageConverter = messageConverter;
    //  }

    /**
     * Whether or not messages should be marked as "durable" when sending them out. Durable messages suffer from a
     * performance penalty, but will survive a reboot of the Message broker that stores them.
     * <p/>
     * By default, messages are durable.
     * <p/>
     * Note that this setting is ignored if a {@link
     * #setMessageConverter(org.axonframework.eventhandling.amqp.AMQPMessageConverter) MessageConverter} is provided.
     * In that case, the message converter must add the properties to reflect the required durability setting.
     *
     * @param durable whether or not messages should be durable
     */
    //  public void setDurable(boolean durable) {
    //       isDurable = durable;
    //   }


    /**
     * Sets the name of the exchange to dispatch published messages to. Defaults to "{@value #DEFAULT_EXCHANGE_NAME}".
     *
     * @param exchangeName the name of the exchange to dispatch messages to
     */
    //   public void setExchangeName(String exchangeName) {
    //       this.exchangeName = exchangeName;
    //  }

    /**
     * Sets the name of the exchange to dispatch published messages to. Defaults to the exchange named "{@value
     * #DEFAULT_EXCHANGE_NAME}".
     *
     * @param exchange the exchange to dispatch messages to
     */
//    public void setExchange(Exchange exchange) {
//        this.exchangeName = exchange.getName();
    //  }

    /**
     * Sets the ListenerContainerLifecycleManager that creates and manages the lifecycle of Listener Containers for the
     * clusters that are connected to this terminal.
     * <p/>
     * Defaults to an autowired ListenerContainerLifecycleManager
     *
     * @param listenerContainerLifecycleManager
     *         the listenerContainerLifecycleManager to set
     */
    //   public void setListenerContainerLifecycleManager(
    //          ListenerContainerLifecycleManager listenerContainerLifecycleManager) {
    //      this.listenerContainerLifecycleManager = listenerContainerLifecycleManager;
    //  }



    public function onClusterCreated(ClusterInterface $cluster)
    {
        $clusterMetaData = $cluster->getMetaData();

        if ($clusterMetaData->getProperty(AMQPConsumerConfigurationInterface::AMQP_CONFIG_PROPERTY) instanceof AMQPConsumerConfigurationInterface) {
            $config = $clusterMetaData->getProperty(AMQPConsumerConfigurationInterface::AMQP_CONFIG_PROPERTY);
        } else {
            $config = new DefaultAMQPConsumerConfiguration($cluster->getName());
        }

         $this->clusters[] = $cluster;
        //getListenerContainerLifecycleManager().registerCluster(cluster, config, messageConverter);
    }

    public function publish(array $events)
    {
        $conn = new \PhpAmqpLib\Connection\AMQPConnection("localhost", 5672,
                "guest", "guest");
        $channel = $conn->channel();

        foreach ($this->clusters as $cluster) {            
            $cluster->publish($events);
        }
        
        try {
            if ($this->waitForAck) {
                $channel->confirm_select();
            }
            foreach ($events as $event) {
                $amqpMessage = $this->messageConverter->createAMQPMessage($event);
                $this->doSendMessage($channel, $amqpMessage);
            }
            if (CurrentUnitOfWork::isStarted()) {
            //    CurrentUnitOfWork::get()->registerListener(new ChannelTransactionUnitOfWorkListener($channel));
            } else if ($this->isTransactional) {
                $channel->tx_commit();
            } else if ($this->waitForAck) {
                $channel->wait_for_pending_acks($this->publisherAckTimeout);
            }
        } catch (\Exception $ex) {
            if ($this->isTransactional) {
                $this->tryRollback($channel);
            }

            throw new EventPublicationFailedException("Failed to dispatch Events to the Message Broker.",
            $ex);
        } finally {
            if (!CurrentUnitOfWork::isStarted()) {
                $this->tryClose($channel);
            }
        }
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

}

class ChannelTransactionUnitOfWorkListener extends UnitOfWorkListenerAdapter
{

    private $isOpen;
    private $channel;

    public function __construct(AMQPChannel $channel)
    {
        $this->channel = $channel;
        $this->isOpen = true;
    }

    public function onPrepareTransactionCommit(UnitOfWorkInterface $unitOfWork,
            $transaction)
    {
        //   if ((isTransactional || waitForAck) && isOpen && !channel.isOpen()) {
        //      throw new EventPublicationFailedException(
        //              "Unable to Commit UnitOfWork changes to AMQP: Channel is closed.", channel.getCloseReason());
        // }
    }

    public function afterCommit(UnitOfWorkInterface $unitOfWork)
    {
        if ($this->isOpen) {
            try {
                if ($this->isTransactional) {
                    $this->channel->tx_commit();
                } else if ($this->waitForAck) {
                    $this->waitForConfirmations();
                }
            } catch (\Exception $ex) {
                //logger.warn("Unable to commit transaction on channel.");             
            }
            $terminal->tryClose($channel);
        }
    }

    private function waitForConfirmations()
    {
        try {
            $channel->waitForConfirmsOrDie($publisherAckTimeout);
        } catch (\Exception $ex) {
            throw new EventPublicationFailedException("Failed to receive acknowledgements for all events");
        }
    }

    public function onRollback(UnitOfWorkInterface $unitOfWork,
            \Exception $failureCause = null)
    {
        /* try {
          if (isTransactional) {
          channel.txRollback();
          }
          } catch (IOException e) {
          logger.warn("Unable to rollback transaction on channel.", e);
          }
          tryClose(channel);
          isOpen = false; */
    }

}